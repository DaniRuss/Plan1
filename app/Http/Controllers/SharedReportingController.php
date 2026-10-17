<?php
namespace App\Http\Controllers;

use App\Models\{Department, User, Plan, ReportingTeam, ReportingSubmission, ReportingEntry};
use App\Services\SharedReporting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SharedReportingController extends Controller
{
    public function __construct(private readonly SharedReporting $reporting) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->hasPermission('view_performance') || $user->isSystemAdmin()),403);
        $departments = Department::where('is_active',true)->when(!$user->hasOrganizationWidePerformanceAccess(),fn ($q) => $q->where('id',$user->department_id))->orderBy('name')->get();
        if ($departments->isEmpty()) return view('shared_reporting.empty');
        $department = (int)($request->input('department_id') ?: $user->department_id ?: $departments->first()?->id);
        $this->reporting->authorizeView($user,$department);
        $team = ReportingTeam::with('coordinator','backup')->where('department_id',$department)->first();
        $years = Plan::where('department_id',$department)->where('status','approved')->distinct()->orderByDesc('year')->pluck('year');
        $reports = ReportingSubmission::with('department')->whereIn('department_id',$user->hasOrganizationWidePerformanceAccess() && !$request->filled('department_id') ? $departments->pluck('id') : [$department])
            ->withCount(['entries','entries as ready_count'=>fn ($q) => $q->whereIn('state',['ready','not_applicable'])])->latest();
        if ($user->isHeadOffice() && !$user->isSystemAdmin() && !$user->isPlanReportHeadOffice()) $reports->where('status','approved_by_head_office');
        $reports = $reports->paginate(20)->withQueryString();
        return view('shared_reporting.index',compact('departments','department','team','years','reports'));
    }

    public function team(Department $department, Request $request)
    {
        abort_unless($this->reporting->canManageTeam($request->user(),$department->id),403);
        $team = ReportingTeam::where('department_id',$department->id)->first();
        $experts = User::active()->where('department_id',$department->id)->whereHas('roles',fn ($q) => $q->where('name',User::ROLE_DEPARTMENT_EXPERT))->orderBy('name')->get();
        return view('shared_reporting.team',compact('department','team','experts'));
    }

    public function updateTeam(Department $department, Request $request)
    {
        $data = $request->validate(['coordinator_id'=>'required|integer','backup_id'=>'required|integer|different:coordinator_id','reason'=>'required|string|min:5|max:2000','version'=>'required|integer|min:0']);
        $this->reporting->team($department,$request->user(),$data);
        return redirect()->route('shared-reporting.index',['department_id'=>$department->id])->with('success','Reporting team saved. New draft periods now use the shared reporting workspace. Existing reports in review keep their legacy workflow.');
    }

    public function start(Request $request)
    {
        $data = $request->validate(['department_id'=>'required|integer|exists:departments,id','year'=>'required|integer|min:1900|max:9999','month'=>'required|in:july,august,september,october,november,december,january,february,march,april,may,june','frequency'=>'required|in:daily,weekly,monthly','week'=>'nullable|integer|min:0|max:6']);
        $data['week'] = $data['frequency'] === 'monthly' ? 0 : (int)($data['week'] ?? 1);
        return redirect()->route('shared-reporting.show',$this->reporting->start($request->user(),$data));
    }

    public function show(ReportingSubmission $submission, Request $request)
    {
        $this->authorizeReport($submission,$request->user());
        $submission->load('department','plan','entries.planItem.strategicObjective','entries.section.reviewer','entries.editor','sections.reviewer','sections.entries');
        $entries = $submission->entries->sort(fn ($a,$b) => strnatcmp($a->planItem->strategicObjective->code,$b->planItem->strategicObjective->code));
        $values = $entries->mapWithKeys(fn ($entry) => [$entry->id=>$this->reporting->values($entry)]);
        $team = ReportingTeam::with('coordinator','backup')->where('department_id',$submission->department_id)->first();
        $reviewers = User::active()->where('department_id',$submission->department_id)->whereHas('roles',fn ($q) => $q->where('name',User::ROLE_DEPARTMENT_DIRECTORATE))->get();
        $days = $submission->frequency === 'daily' ? $this->reporting->days($submission) : [];
        $dayLabels = $days ? \App\Services\CalendarHelper::getMonthWeeks($submission->month,(int)$submission->year)[$submission->week]['days'] : [];
        $reporting = $this->reporting;
        $legacyAttachments = \App\Models\PerformanceAttachment::with('uploader')->where(['department_id'=>$submission->department_id,'year'=>$submission->year,
            'month'=>$submission->month,'frequency'=>$submission->frequency,'week_number'=>$submission->frequency==='monthly' ? null : $submission->week])->get();
        $history = \App\Models\AuditLog::with('user')->where(function ($q) use ($submission,$entries) {
            $q->where(fn ($q) => $q->where('model_type',ReportingSubmission::class)->where('model_id',$submission->id))
                ->orWhere(fn ($q) => $q->where('model_type',ReportingEntry::class)->whereIn('model_id',$entries->pluck('id')));
        })->latest()->limit(30)->get();
        return view('shared_reporting.show',compact('submission','entries','values','team','reviewers','days','dayLabels','reporting','history','legacyAttachments'));
    }

    public function save(ReportingEntry $entry, Request $request)
    {
        $data = $request->validate(['version'=>'required|integer|min:1','state'=>'required|in:draft,ready,not_applicable','actual'=>'nullable','planned'=>'nullable|numeric|min:0',
            'days'=>'nullable|array','days.*'=>'nullable|numeric','metadata'=>'nullable|array','metadata.*'=>'nullable|numeric|min:0','stations'=>'nullable|array|max:50','stations.*'=>'nullable|numeric',
            'notes'=>'nullable|string|max:5000','reason'=>'nullable|string|max:2000','files'=>'nullable|array|max:10','files.*'=>'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png']);
        $this->reporting->save($entry,$request->user(),$data,$request->file('files',[]));
        return back()->with('success','Entry saved. Other experts’ entries were not changed.');
    }

    public function saveAll(ReportingSubmission $submission, Request $request)
    {
        $this->authorizeReport($submission,$request->user());
        $rules = ['version'=>'required|integer|min:1','state'=>'required|in:draft,ready,not_applicable','actual'=>'nullable','planned'=>'nullable|numeric|min:0',
            'days'=>'nullable|array','days.*'=>'nullable|numeric','metadata'=>'nullable|array','metadata.*'=>'nullable|numeric|min:0','stations'=>'nullable|array|max:50','stations.*'=>'nullable|numeric',
            'notes'=>'nullable|string|max:5000','reason'=>'nullable|string|max:2000','files'=>'nullable|array|max:10','files.*'=>'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'];
        $batchRules = ['entries'=>'required|array|min:1'];
        foreach ($rules as $key=>$rule) $batchRules['entries.*.'.$key] = $rule;
        $data = $request->validate($batchRules);
        $storedPaths = [];
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($submission,$request,$data,&$storedPaths) {
                ReportingSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                foreach ($data['entries'] as $id=>$values) {
                    $entry = $submission->entries()->whereKey($id)->firstOrFail();
                    abort_unless($this->reporting->editable($request->user(),$entry),403);
                    abort_if((int)$values['version'] !== (int)$entry->version,409,'Another expert changed an activity. Reload before saving. No activities were saved.');
                    $beforePaths = array_column($entry->evidence_paths ?? [],'path');
                    $this->reporting->save($entry,$request->user(),$values,$request->file('entries.'.$id.'.files',[]));
                    $storedPaths = array_merge($storedPaths,array_diff(array_column($entry->fresh()->evidence_paths ?? [],'path'),$beforePaths));
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPaths);
            throw $e;
        }
        return redirect()->route('shared-reporting.show',$submission)->with('success','All editable activities and Completion states saved.');
    }

    public function assign(ReportingEntry $entry, Request $request)
    {
        $data = $request->validate(['version'=>'required|integer|min:1','reviewer_id'=>'required|integer','reason'=>'required|string|min:5|max:2000','evidence_required'=>'nullable|boolean']);
        $this->reporting->assign($entry,$request->user(),$data);
        return back()->with('success','Reviewer assignment saved and recorded in the audit history.');
    }

    public function transition(ReportingSubmission $submission, Request $request)
    {
        $data = $request->validate(['version'=>'required|integer|min:1','action'=>'required|string','note'=>'nullable|string|max:2000','section_id'=>'nullable|integer','sections'=>'nullable|array','sections.*'=>'integer|distinct']);
        $this->reporting->transition($submission,$request->user(),$data);
        return back()->with('success','Report workflow updated.');
    }

    public function evidence(ReportingEntry $entry, int $index, Request $request)
    {
        $this->authorizeReport($entry->submission,$request->user());
        $file = $entry->evidence_paths[$index] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']),404);
        return Storage::disk('local')->download($file['path'],$file['name'],['Cache-Control'=>'private, no-store']);
    }

    private function authorizeReport(ReportingSubmission $s, User $user): void
    {
        $this->reporting->authorizeView($user,(int)$s->department_id);
        abort_if($user->isHeadOffice() && !$user->isSystemAdmin() && !$user->isPlanReportHeadOffice() && $s->status !== 'approved_by_head_office',403);
    }
}
