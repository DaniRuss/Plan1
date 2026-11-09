<?php

namespace App\Http\Controllers;

use App\Models\PeriodWeightDistribution;
use App\Models\Plan;
use App\Services\PeriodWeightNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodWeightDistributionController extends Controller
{
    public function edit(Request $request, PeriodWeightNormalizer $resolver)
    {
        $this->authorizeRole();
        $year = (int) $request->get('year', Plan::where('status','approved')->max('year') ?? now()->year);
        $period = strtolower($request->get('period', 'july'));
        $departmentId = (int) $request->get('department_id', Plan::where('year',$year)->where('status','approved')->value('department_id'));
        abort_unless(in_array($period, $this->periods(), true), 422);
        $plan = Plan::with(['department','items.strategicObjective.parent'])->where(['year'=>$year,'department_id'=>$departmentId,'status'=>'approved'])->latest('updated_at')->firstOrFail();
        $distribution = PeriodWeightDistribution::with('allocations')->firstOrNew(['department_id'=>$departmentId,'year'=>$year,'period'=>$period], ['mode'=>'manual','status'=>'draft']);
        $existing = $distribution->allocations->keyBy('plan_item_id');
        $planning = $resolver->planningForPeriod($plan->items, $period);
        if ($distribution->status === 'approved') {
            foreach ($plan->items as $item) $planning[$item->id] = $existing->get($item->id)?->effective_weight !== null;
        }
        $groups = $plan->items
            ->filter(fn($item) => $item->strategicObjective
                && !$item->strategicObjective->is_reference_only
                && !$item->strategicObjective->exclude_from_overall_score)
            ->groupBy(fn($item) => $item->strategicObjective->parent_id ?? 'root')
            ->filter(function ($siblings) use ($planning) {
                $plannedCount = $siblings->filter(fn($item) => $planning[$item->id] ?? false)->count();
                return $plannedCount > 0 && $plannedCount < $siblings->count();
            });
        $departments = \App\Models\Department::whereHas('plans', fn($q)=>$q->where('year',$year)->where('status','approved'))->orderBy('name')->get();
        $years = Plan::where('status','approved')->distinct()->orderByDesc('year')->pluck('year');
        return view('period_weights.edit', compact('year','period','plan','distribution','existing','groups','departments','years','planning'));
    }

    public function store(Request $request, PeriodWeightNormalizer $resolver)
    {
        abort_unless(auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isSystemAdmin(), 403);
        $data = $request->validate(['department_id'=>'required|exists:departments,id','year'=>'required|integer','period'=>'required|in:'.implode(',',$this->periods()),'additions'=>'required|array','additions.*'=>'nullable|numeric|min:0','action'=>'required|in:save,submit']);
        $plan = Plan::with(['items.strategicObjective'])->where(['department_id'=>$data['department_id'],'year'=>$data['year'],'status'=>'approved'])->latest('updated_at')->firstOrFail();
        $distribution = PeriodWeightDistribution::firstOrNew(['department_id'=>$data['department_id'],'year'=>$data['year'],'period'=>$data['period']], ['mode'=>'manual','status'=>'draft']);
        abort_unless(!$distribution->exists || in_array($distribution->status,['draft','returned'],true), 422, 'This distribution is locked in review.');

        $planning = $resolver->planningForPeriod($plan->items, $data['period']);
        $scorable = $plan->items->filter(fn($item)=>$item->strategicObjective && !$item->strategicObjective->is_reference_only && !$item->strategicObjective->exclude_from_overall_score);
        $groups = $scorable->groupBy(fn($item)=>$item->strategicObjective->parent_id ?? 'root');
        $errors = []; $effectiveWeights = []; $editableIds = [];
        foreach ($groups as $key => $siblings) {
            $planned = $siblings->filter(fn($item)=>$planning[$item->id] ?? false);
            $total = (float)$siblings->sum(fn($item)=>$resolver->baseWeight($item));
            $original = (float)$planned->sum(fn($item)=>$resolver->baseWeight($item));
            $available = round(max(0, $total - $original), 10);
            $manual = $planned->count() > 1 && $planned->count() < $siblings->count();
            $assigned = 0;
            foreach ($siblings as $item) {
                if (!($planning[$item->id] ?? false)) { $effectiveWeights[$item->id] = null; continue; }
                $extra = $manual ? (float)($data['additions'][$item->id] ?? 0) : ($planned->count() === 1 ? $available : 0);
                if ($manual) $editableIds[] = $item->id;
                if ($manual && $extra > $available + 0.0001) $errors[] = "Group {$key}: extra allocation exceeds the available weight.";
                $assigned += $extra;
                $effectiveWeights[$item->id] = $resolver->baseWeight($item) + $extra;
            }
            if ($manual && abs($available-$assigned) > 0.0001) $errors[] = "Group {$key}: distribute only ".\App\Support\NumberFormatter::format($available,3)." extra weight; assigned ".\App\Support\NumberFormatter::format($assigned,3)."; remaining ".\App\Support\NumberFormatter::format($available-$assigned,3).'.';
        }
        foreach ($data['additions'] as $id => $value) {
            if (!in_array((int)$id, $editableIds, true) && (float)$value != 0) $errors[] = 'Extra weight can only be assigned to planned activities in a group requiring redistribution.';
        }
        if ($errors) return back()->withInput()->withErrors(['additions'=>implode(' ', $errors)]);

        DB::transaction(function() use($distribution,$data,$scorable,$resolver,$effectiveWeights) {
            $distribution->fill(['mode'=>'manual','status'=>$data['action']==='submit'?'submitted_to_directorate':'draft','assigned_by'=>auth()->id(),'assigned_at'=>now()])->save();
            foreach ($scorable as $item) {
                $distribution->allocations()->updateOrCreate(['plan_item_id'=>$item->id],['original_weight'=>$resolver->baseWeight($item),'effective_weight'=>$effectiveWeights[$item->id]]);
            }
        });
        \App\Models\AuditLog::log('period_weights_saved', $distribution, "{$data['period']} {$data['year']} manual period weights {$data['action']}");
        if ($data['action']==='submit') app(\App\Services\WorkflowNotificationService::class)->roles('plan_report_directorate','Period weights awaiting review',strtoupper($data['period'])." {$data['year']} manual weights were submitted.",route('period-weights.edit',['department_id'=>$data['department_id'],'year'=>$data['year'],'period'=>$data['period']]),'info',null,auth()->user());
        return back()->with('success', $data['action']==='submit'?'Weight distribution sent to Directorate.':'Weight distribution saved.');
    }

    public function transition(Request $request)
    {
        $distribution=PeriodWeightDistribution::findOrFail($request->validate(['distribution_id'=>'required|exists:period_weight_distributions,id','action'=>'required|in:return,send_to_head,approve,reopen','reason'=>'nullable|string|max:1000'])['distribution_id']);
        $action=$request->input('action'); $user=auth()->user();
        if($action==='return'){abort_unless($user->isPlanReportDirectorate()&&$distribution->status==='submitted_to_directorate',403);$distribution->update(['status'=>'returned','change_reason'=>$request->input('reason')]);}
        elseif($action==='send_to_head'){abort_unless($user->isPlanReportDirectorate()&&$distribution->status==='submitted_to_directorate',403);$distribution->update(['status'=>'submitted_to_head','directorate_reviewed_by'=>$user->id,'directorate_reviewed_at'=>now()]);}
        elseif($action==='approve'){abort_unless($user->isPlanReportHeadOffice()&&$distribution->status==='submitted_to_head',403);$distribution->update(['status'=>'approved','approved_by'=>$user->id,'approved_at'=>now()]);}
        else{abort_unless($user->isPlanReportHeadOffice()&&$distribution->status==='approved',403);abort_unless(filled($request->input('reason')),422,'A reopening reason is required.');$distribution->update(['status'=>'returned','change_reason'=>$request->input('reason'),'approved_by'=>null,'approved_at'=>null]);}
        \App\Models\AuditLog::log('period_weights_'.$action, $distribution, "Period weight distribution {$action}. ".($request->input('reason') ?? ''));
        if($action==='send_to_head') app(\App\Services\WorkflowNotificationService::class)->roles('plan_report_head_office','Period weights awaiting approval',strtoupper($distribution->period)." {$distribution->year} manual weights await approval.",route('period-weights.edit',['department_id'=>$distribution->department_id,'year'=>$distribution->year,'period'=>$distribution->period]),'info',null,$user);
        return back()->with('success','Weight distribution workflow updated.');
    }

    private function authorizeRole(): void { abort_unless(auth()->user()->isSystemAdmin()||auth()->user()->isPlanReportDepartmentExpert()||auth()->user()->isPlanReportDirectorate()||auth()->user()->isPlanReportHeadOffice(),403); }
    private function periods(): array { return ['july','august','september','october','november','december','january','february','march','april','may','june','q1','q2','q3','q4']; }
}
