<?php

namespace App\Services;

use App\Models\{Plan, WeeklyPerformance, PeriodicPerformance, ReportingSubmission};
use Illuminate\Support\Carbon;

class QualitySentinel
{
    public function scan(int $year, string $month, ?int $departmentId = null): array
    {
        $plans = Plan::where('year',$year)->where('status','approved')
            ->when($departmentId,fn($q)=>$q->where('department_id',$departmentId))
            ->with(['department','items.strategicObjective.children','items.assignments'])->latest('updated_at')->latest('id')->get()->unique('department_id');
        $items = $plans->flatMap->items;
        $weekly = WeeklyPerformance::whereIn('plan_item_id',$items->pluck('id'))->where('year',$year)->where('month',$month)->get()
            ->keyBy(fn($r)=>$r->plan_item_id.':'.$r->week_number);
        $monthly = PeriodicPerformance::whereIn('plan_item_id',$items->pluck('id'))->whereIn('period',[$month,$year.'-'.$month])->get()
            ->sortBy(fn($r)=>$r->period===$year.'-'.$month ? 0 : 1)->unique('plan_item_id')->keyBy('plan_item_id');
        $submissions = ReportingSubmission::where('year',$year)->where('month',$month)->whereIn('department_id',$plans->pluck('department_id'))
            ->with('entries')->get()->keyBy(fn($s)=>$s->department_id.':'.$s->frequency.':'.$s->week);
        $weeks = CalendarHelper::getMonthWeeks($month,$year);
        $monthEnd = Carbon::create(CalendarHelper::getGregorianYear($month,$year),CalendarHelper::monthNameToNumber($month),1)->endOfMonth();
        $alerts=[]; $departments=[];
        foreach ($plans as $plan) {
            $summary=['id'=>$plan->department_id,'name'=>$plan->department->name,'expected'=>0,'reported'=>0,'approved'=>0,'exempt'=>0,'issues'=>0];
            foreach ($plan->items as $item) {
                $objective=$item->strategicObjective;
                if (!$objective || $objective->status!=='approved' || $objective->is_reference_only || $item->{$month.'_target'}===null
                    || ($objective->children->isNotEmpty() && $objective->summary_type!=='manual_override')
                    || !$item->assignments->contains(fn($a)=>(int)$a->performer_id===(int)$plan->department_id && $a->status==='approved_for_experts')) continue;
                $item->setRelation('plan',$plan);
                $frequency=$item->effectiveReportingFrequency();
                $slots=$frequency==='monthly' ? [0=>['due_at'=>$monthEnd->toDateTimeString()]] : $weeks;
                foreach ($slots as $week=>$slot) {
                    if (now()->lt(Carbon::parse($slot['due_at']))) continue;
                    $summary['expected']++;
                    $submission=$submissions->get($plan->department_id.':'.$frequency.':'.$week);
                    $entry=$submission?->entries->firstWhere('plan_item_id',$item->id);
                    $period=$frequency==='monthly' ? ucfirst($month) : 'Week '.$week;
                    $url=$submission ? route('shared-reporting.show',$submission) : ($frequency==='monthly'
                        ? route('monthly.entry.form',[$year,$month,'department_id'=>$plan->department_id])
                        : route($frequency==='daily' ? 'daily.entry' : 'weekly.entry',[$year,$month,$week,'department_id'=>$plan->department_id]));
                    $base=['department_id'=>$plan->department_id,'department'=>$plan->department->name,'activity'=>$objective->description,
                        'code'=>$objective->code,'period'=>$period,'frequency'=>$frequency,'url'=>$url];
                    if ($entry?->state==='not_applicable' && $submission->status==='approved_by_head_office') {
                        $summary['exempt']++; $summary['approved']++; continue;
                    }
                    $row=$frequency==='monthly' ? $monthly->get($item->id) : $weekly->get($item->id.':'.$week);
                    if (!$row || $row->actual_value===null) {
                        $alerts[]=$base+['severity'=>'critical','rule'=>'missing','title'=>'Actual value missing','detail'=>'This planned activity has no actual for a reporting period that is due. Enter a value or complete the not-applicable review.'];
                        continue;
                    }
                    $summary['reported']++;
                    $state=$submission?->status ?? $row->workflow_status ?? $row->status;
                    $approved=$submission ? $state==='approved_by_head_office' : ($state==='approved_by_head_office' || (!$row->workflow_status && $row->status==='approved'));
                    if ($approved) $summary['approved']++;
                    else $alerts[]=$base+['severity'=>'review','rule'=>'approval','title'=>'Final approval outstanding','detail'=>'Actual recorded; current stage: '.str_replace('_',' ',$state).'. Open the report to continue the review.'];
                    $target=$frequency==='monthly' ? (float)$item->{$month.'_target'} : (float)$row->planned_value;
                    $actual=(float)$row->actual_value;
                    if ($target<=0 && $actual>0) {
                        $alerts[]=$base+['severity'=>'warning','rule'=>'zero_plan','title'=>'Actual against a zero plan','detail'=>'Actual: '.number_format($actual,3).'. Verify the approved plan and the unit before accepting the result.'];
                    } elseif ($target>0) {
                        $ratio=$objective->goal_direction==='decrease' ? ($actual>0 ? $target/$actual*100 : null) : $actual/$target*100;
                        if ($ratio!==null && $ratio>200) $alerts[]=$base+['severity'=>'warning','rule'=>'outlier','title'=>'Achievement above 200%','detail'=>number_format($ratio,1).'% uncapped achievement. Actual: '.number_format($actual,3).'; plan: '.number_format($target,3).'. Verify source evidence; high achievement may be valid.'];
                    }
                }
            }
            $summary['issues']=count(array_filter($alerts,fn($a)=>$a['department_id']===$plan->department_id));
            $summary['coverage']=$summary['expected'] ? round(($summary['reported']+$summary['exempt'])/$summary['expected']*100,1) : null;
            $departments[]=$summary;
        }
        $expected=array_sum(array_column($departments,'expected'));
        $reported=array_sum(array_column($departments,'reported'));
        $exempt=array_sum(array_column($departments,'exempt'));
        $approved=array_sum(array_column($departments,'approved'));
        $priority=['critical'=>0,'warning'=>1,'review'=>2];
        usort($alerts,fn($a,$b)=>[$priority[$a['severity']],$a['department'],$a['code'],$a['period']] <=> [$priority[$b['severity']],$b['department'],$b['code'],$b['period']]);
        return compact('alerts','departments')+['stats'=>[
            'expected'=>$expected,'reported'=>$reported,'exempt'=>$exempt,'approved'=>$approved,
            'coverage'=>$expected ? round(($reported+$exempt)/$expected*100,1) : null,
            'approval_rate'=>$expected ? round($approved/$expected*100,1) : null,
            'critical'=>count(array_filter($alerts,fn($a)=>$a['severity']==='critical')),
            'warning'=>count(array_filter($alerts,fn($a)=>$a['severity']==='warning')),
            'review'=>count(array_filter($alerts,fn($a)=>$a['severity']==='review')),
        ]];
    }
}
