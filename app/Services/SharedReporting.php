<?php
namespace App\Services;

use App\Models\{Department, User, ReportingTeam, ReportingSubmission, ReportingReviewSection, ReportingEntry, Plan, PlanItem, WeeklyPerformance, DailyPerformance, PeriodicPerformance, PeriodClosure, AuditLog};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SharedReporting
{
    public function canManageTeam(User $user, int $department): bool
    {
        return $user->is_active && ($user->isSystemAdmin() || ($user->isDepartmentChief() && (int)$user->department_id === $department));
    }

    public function authorizeView(User $user, int $department): void
    {
        abort_unless($user->is_active && ($user->isSystemAdmin() || ($user->hasPermission('view_performance') && ($user->hasOrganizationWidePerformanceAccess() || (int)$user->department_id === $department))),403);
    }

    public function team(Department $department, User $actor, array $data): ReportingTeam
    {
        abort_unless($this->canManageTeam($actor,$department->id),403);
        return DB::transaction(function () use ($department,$actor,$data) {
            Department::whereKey($department->id)->lockForUpdate()->firstOrFail();
            $team = ReportingTeam::where('department_id',$department->id)->first();
            abort_if((int)($data['version'] ?? 0) !== (int)($team?->version ?? 0),409,'The reporting team changed. Reload before saving.');
            foreach (['coordinator_id','backup_id'] as $field) {
                $person = User::findOrFail($data[$field]);
                $this->check($person->is_active && $person->isDepartmentExpert() && (int)$person->department_id === $department->id,'Choose active department experts for the reporting team.');
            }
            $this->check((int)$data['coordinator_id'] !== (int)$data['backup_id'],'Coordinator and backup must be different people.');
            $before = $team?->only('coordinator_id','backup_id','version');
            $team = ReportingTeam::updateOrCreate(['department_id'=>$department->id],['coordinator_id'=>$data['coordinator_id'],'backup_id'=>$data['backup_id'],'version'=>($team?->version ?? 0)+1]);
            AuditLog::log('reporting team assigned',$team,['before'=>$before,'after'=>$team->only('coordinator_id','backup_id','version'),'reason'=>$data['reason'],'actor'=>$actor->id]);
            DB::afterCommit(fn () => app(WorkflowNotificationService::class)->users(User::active()->whereIn('id',[$team->coordinator_id,$team->backup_id])->get(),'Reporting team assignment','You have been assigned to the reporting team for '.$department->name.'.',route('shared-reporting.index',['department_id'=>$department->id]),'info',$actor));
            return $team;
        });
    }

    public function coordinator(User $user, ReportingSubmission $submission): bool
    {
        if (!$user->is_active) return false;
        if ($user->isSystemAdmin()) return true;
        $team = ReportingTeam::where('department_id',$submission->department_id)->first();
        return $user->isDepartmentExpert() && (int)$user->department_id === (int)$submission->department_id && $team && in_array($user->id,[(int)$team->coordinator_id,(int)$team->backup_id],true);
    }

    public function start(User $user, array $period): ReportingSubmission
    {
        $this->authorizeView($user,(int)$period['department_id']);
        abort_unless($user->isSystemAdmin() || ($user->isDepartmentExpert() && (int)$user->department_id === (int)$period['department_id']),403);
        $this->check(ReportingTeam::where('department_id',$period['department_id'])->exists(),'The department chief must configure a coordinator and backup first.');
        $this->validatePeriod($period);
        return DB::transaction(function () use ($user,$period) {
            Department::whereKey($period['department_id'])->lockForUpdate()->firstOrFail();
            $key = collect($period)->only(['department_id','year','month','frequency','week'])->all();
            $existing = ReportingSubmission::where($key)->first();
            if ($existing) return $existing;
            $plan = Plan::where(['department_id'=>$period['department_id'],'year'=>$period['year'],'status'=>'approved'])->latest('updated_at')->first();
            $this->check($plan !== null,'An approved department plan is required.');
            $items = $plan->items()->forReportingFrequency($period['frequency'])->approvedForDepartment((int)$period['department_id'])->with('strategicObjective.children','assignments')->get()
                ->filter(fn ($item) => $item->strategicObjective?->status === 'approved' && $item->{$period['month'].'_target'} !== null);
            $this->check($items->isNotEmpty(),'No approved assigned objectives are planned for this period and reporting frequency.');
            $legacy = $this->metricQuery($period,$plan->items()->forReportingFrequency($period['frequency'])->pluck('id')->all())->get();
            $this->check(!$legacy->contains(fn ($row) => !in_array($row->workflow_status,[null,'','draft'],true) || $row->status === 'approved'),'This period is already in the legacy approval workflow. Complete it there; historical approvals are not migrated.');
            $submission = ReportingSubmission::create($key+['plan_id'=>$plan->id]);
            foreach ($items as $item) {
                if ($item->strategicObjective->children->isNotEmpty() && $item->strategicObjective->summary_type !== 'manual_override') continue;
                $reviewer = $item->assignments->first(fn ($a) => (int)$a->performer_id === (int)$period['department_id'] && $a->status === 'approved_for_experts')?->assigned_directorate_user_id;
                $section = $reviewer && $this->validReviewer((int)$reviewer,(int)$period['department_id'])
                    ? $submission->sections()->firstOrCreate(['reviewer_id'=>$reviewer]) : null;
                ReportingEntry::create(['submission_id'=>$submission->id,'plan_item_id'=>$item->id,'section_id'=>$section?->id,
                    'state'=>$legacy->contains('plan_item_id',$item->id) ? 'draft' : 'missing']);
            }
            $this->check($submission->entries()->exists(),'No independently reportable activities exist for this period.');
            AuditLog::log('shared report started',$submission,['actor'=>$user->id]);
            return $submission;
        });
    }

    public function editable(User $user, ReportingEntry $entry): bool
    {
        if (!$user->is_active) return false;
        $s = $entry->submission;
        if (!$user->isSystemAdmin() && (int)$user->department_id !== (int)$s->department_id) return false;
        if (in_array($s->status,['submitted_to_chief','returned_to_department_chief'],true)) return $this->canManageTeam($user,(int)$s->department_id);
        if ($s->status === 'submitted_to_directorate' && $entry->section?->status === 'submitted_to_directorate')
            return $user->isSystemAdmin() || ($entry->section->reviewer_id === $user->id && $user->isDepartmentDirectorate());
        return ($user->isSystemAdmin() || $user->isDepartmentExpert())
            && in_array($s->status,['draft','submitted_to_directorate'],true)
            && (!$entry->section || in_array($entry->section->status,['draft','returned_to_expert'],true));
    }

    public function save(ReportingEntry $entry, User $user, array $data, array $files = []): void
    {
        $paths = [];
        try {
            DB::transaction(function () use ($entry,$user,$data,$files,&$paths) {
                $submission = ReportingSubmission::whereKey($entry->submission_id)->lockForUpdate()->firstOrFail();
                $entry = ReportingEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
                $entry->setRelation('submission',$submission);
                abort_unless($this->editable($user,$entry),403,'This reporting section is locked for editing.');
                abort_if((int)$data['version'] !== (int)$entry->version,409,'Another expert changed this entry. Reload and compare their values before saving.');
                $this->validatePeriod($submission->toArray(),true);
                $this->check($entry->planItem->effectiveReportingFrequency() === $submission->frequency,'Reporting frequency changed. Ask the chief to resolve this period before editing.');
                $before = $this->values($entry);
                $changed = $this->entryChanged($entry,$data,$before);
                if ($changed && !$user->isDepartmentExpert() && !$user->isSystemAdmin() && mb_strlen(trim($data['reason'] ?? '')) < 5) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'entries.'.$entry->id.'.reason'=>'Activity '.$entry->planItem->strategicObjective->code.': enter a correction reason of at least 5 characters for this changed activity.',
                    ]);
                }
                if (!$changed && !$files && ($data['reason'] ?? null) == $entry->reason) return;
                // An unfinished blank row can be saved as a draft without inventing a zero.
                $hasInput = collect([$data['actual'] ?? null,...array_values($data['days'] ?? []),...array_values($data['metadata'] ?? []),...array_values($data['stations'] ?? [])])
                    ->contains(fn ($value) => $value !== null && $value !== '');
                if ($data['state'] === 'draft' && !$hasInput && $before['actual'] === null && !$files) {
                    $entry->update(['state'=>'draft','reason'=>$data['reason'] ?? null,'updated_by'=>$user->id,'version'=>$entry->version+1]);
                    $submission->increment('version');
                    if ($entry->section_id) $entry->section()->increment('version');
                    AuditLog::log('shared report entry saved',$entry,['before'=>$before,'after'=>$this->values($entry),'state'=>'draft','actor'=>$user->id]);
                    return;
                }
                $this->check(count($entry->evidence_paths ?? [])+count($files)<=10,'A reporting activity can have at most 10 evidence files.');
                $notApplicable = $data['state'] === 'not_applicable';
                $this->check(!$notApplicable || mb_strlen(trim($data['reason'] ?? '')) >= 5,'Explain why this item is not applicable.');
                $item = $entry->planItem;
                if ($notApplicable) {
                    $this->metricQuery($submission->toArray(),[$item->id])->delete();
                    if ($submission->frequency === 'daily') DailyPerformance::where($this->dailyKey($submission,$item->id))->delete();
                } else {
                    $actual = $data['actual'] ?? null;
                    if ($submission->frequency === 'daily') {
                        $days = $this->days($submission);
                        $inputDays = $data['days'] ?? [];
                        $this->check(!array_diff(array_keys($inputDays),$days),'A daily value is outside this reporting week.');
                        foreach ($days as $day) {
                            $value = $inputDays[$day] ?? null;
                            $this->check($data['state'] !== 'ready' || ($value !== null && $value !== ''),'Enter every day, including explicit zeros, before marking this row ready.');
                            $key = $this->dailyKey($submission,$item->id)+['day_of_week'=>$day];
                            if ($value === null || $value === '') DailyPerformance::where($key)->delete();
                            else DailyPerformance::updateOrCreate($key,['actual_value'=>$value,'created_by'=>$user->id,'notes'=>$data['notes'] ?? null]);
                        }
                        DailyPerformance::where($this->dailyKey($submission,$item->id))->whereNotIn('day_of_week',$days)->delete();
                        $daily = DailyPerformance::where($this->dailyKey($submission,$item->id))->get();
                        $actual = $daily->isEmpty() ? null : $daily->sum('actual_value');
                    } elseif ($submission->frequency === 'weekly' && $item->strategicObjective->template_type === 'machinery') {
                        $meta = $data['metadata'] ?? [];
                        $this->check(isset($meta['qty'],$meta['time'],$meta['days']),'Quantity, hours, and days are required for machinery.');
                        $actual = FidelityCalculator::calculateMachineryPlan((float)$meta['qty'],(float)$meta['time'],(float)$meta['days']);
                    } elseif ($submission->frequency === 'weekly' && $item->strategicObjective->template_type === 'truck_haulage') {
                        $meta = $data['metadata'] ?? [];
                        $this->check(isset($meta['count'],$meta['cycle'],$meta['tone']),'Truck count, cycle, and tonnage are required.');
                        $actual = FidelityCalculator::calculateTruckPlan((float)$meta['count'],(float)$meta['cycle'],(float)$meta['tone']);
                    } elseif ($item->strategicObjective->isTemporal() && is_string($actual) && str_contains($actual,':')) {
                        $this->check((bool)preg_match('/^\d+:([0-5]\d)$/',$actual),'Enter time as hours:minutes.');
                        $actual = FidelityCalculator::parseComplexTemporal($actual);
                    }
                    if ($submission->frequency === 'weekly' && !empty($data['stations'])) {
                        $this->check(!collect($data['stations'])->contains(fn ($value) => $value === null || $value === ''),'Enter a value for each station, including zero where appropriate.');
                        $actual = array_sum($data['stations']);
                    }
                    $this->check($actual !== null && $actual !== '' && is_numeric($actual),'Enter an actual value. A blank value is not zero.');
                    if ($submission->frequency === 'weekly' && !$item->strategicObjective->isTemporal() && !in_array($item->strategicObjective->template_type,['machinery','truck_haulage'],true)) $actual = FidelityCalculator::scaleRevenueValue((float)$actual,$item->strategicObjective->unit);
                    $this->persistMetric($entry,$user,(float)$actual,$data);
                }
                foreach ($files as $file) {
                    $path = $file->store('shared_reporting/'.$submission->id.'/'.$entry->id,'local');
                    if (!$path) throw new \RuntimeException('Evidence could not be stored.');
                    $paths[] = ['path'=>$path,'name'=>$file->getClientOriginalName()];
                }
                $entry->update(['state'=>$data['state'],'reason'=>$data['reason'] ?? null,'updated_by'=>$user->id,'version'=>$entry->version+1,
                    'evidence_paths'=>array_merge($entry->evidence_paths ?? [],$paths)]);
                $this->recalculate($submission,$user);
                $submission->increment('version');
                if ($entry->section_id) $entry->section()->increment('version');
                AuditLog::log('shared report entry saved',$entry,['before'=>$before,'after'=>$this->values($entry),'state'=>$entry->state,'reason'=>$entry->reason,'actor'=>$user->id]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete(array_column($paths,'path'));
            throw $e;
        }
    }

    private function entryChanged(ReportingEntry $entry, array $data, array $before): bool
    {
        $same = function ($a,$b) use (&$same): bool {
            if (is_array($a) && is_array($b)) {
                if (count($a) !== count($b)) return false;
                foreach ($a as $key=>$value) if (!array_key_exists($key,$b) || !$same($value,$b[$key])) return false;
                return true;
            }
            if ($a === '') $a = null;
            if ($b === '') $b = null;
            if ($a === null || $b === null) return $a === $b;
            return is_numeric($a) && is_numeric($b) ? $a == $b : $a === $b;
        };
        if ($data['state'] !== $entry->state || !$same($data['notes'] ?? null,$before['notes'])) return true;
        $s = $entry->submission;
        $template = $entry->planItem->strategicObjective->template_type;
        $field = $s->frequency === 'daily' ? 'days' : ($s->frequency === 'weekly' && in_array($template,['machinery','truck_haulage'],true) ? 'metadata' : 'actual');
        $previous = $before[$field];
        if ($field === 'metadata') $previous = array_intersect_key($previous,array_flip($template === 'machinery' ? ['qty','time','days'] : ['count','cycle','tone']));
        if (!$same($data[$field] ?? ($field === 'actual' ? null : []),$previous)) return true;
        if (array_key_exists('stations',$data) && !$same($data['stations'],$before['stations'])) return true;
        return false;
    }

    public function values(ReportingEntry $entry): array
    {
        $submission = $entry->submission;
        $query = $this->metricQuery($submission->toArray(),[$entry->plan_item_id]);
        $row = $submission->frequency === 'monthly'
            ? (clone $query)->orderByRaw('CASE WHEN period = ? THEN 0 ELSE 1 END',[$submission->year.'-'.$submission->month])->first()
            : $query->first();
        return ['actual'=>$row?->actual_value,'planned'=>$row?->planned_value,'notes'=>$row?->notes,'metadata'=>$row?->metadata ?? [],
            'source_rows'=>$submission->frequency === 'monthly' ? $query->get(['id','period','actual_value','notes','workflow_status'])->toArray() : [],
            'stations'=>$row instanceof WeeklyPerformance ? $row->stationData->pluck('actual_value','station_name')->all() : [],
            'days'=>$submission->frequency === 'daily' ? DailyPerformance::where($this->dailyKey($submission,$entry->plan_item_id))->pluck('actual_value','day_of_week')->all() : []];
    }

    public function assign(ReportingEntry $entry, User $actor, array $data): void
    {
        DB::transaction(function () use ($entry,$actor,$data) {
            $s = ReportingSubmission::whereKey($entry->submission_id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManageTeam($actor,(int)$s->department_id),403);
            $this->check(in_array($s->status,['draft','submitted_to_directorate','submitted_to_chief','returned_to_department_chief'],true),'Assignments cannot change during external review or after final approval.');
            $this->validatePeriod($s->toArray());
            $entry->refresh();
            abort_if((int)$entry->version !== (int)$data['version'],409,'This assignment changed. Reload before saving.');
            $this->check($this->validReviewer((int)$data['reviewer_id'],(int)$s->department_id),'Choose an active directorate in this department.');
            $before = $entry->section_id;
            $previousReviewer = $entry->section?->reviewer_id;
            $section = $s->sections()->firstOrCreate(['reviewer_id'=>$data['reviewer_id']]);
            // A replacement reviewer must review their full section afresh.
            $section->update(['status'=>'draft','reviewed_at'=>null,'version'=>$section->version+1]);
            $entry->update(['section_id'=>$section->id,'evidence_required'=>$data['evidence_required'] ?? false,'version'=>$entry->version+1]);
            $s->sections()->whereDoesntHave('entries')->delete();
            $s->update(['status'=>$s->status === 'draft' ? 'draft' : 'submitted_to_directorate','version'=>$s->version+1]);
            $this->synchronize($s);
            AuditLog::log('report reviewer assigned',$entry,['previous_section'=>$before,'previous_reviewer'=>$previousReviewer,'reviewer'=>$data['reviewer_id'],'reason'=>$data['reason'],'actor'=>$actor->id]);
            DB::afterCommit(function () use ($s,$actor,$data) {
                $users = User::active()->where('department_id',$s->department_id)->whereKey($data['reviewer_id'])->get();
                app(WorkflowNotificationService::class)->users($users,'Shared reporting reviewer assigned',
                    $s->department->name.' / '.$s->year.' '.ucfirst($s->month).' / '.ucfirst($s->frequency).': You have been assigned a reporting activity. Submitters must send the section before review.',
                    route('shared-reporting.show',$s),'info',$actor);
            });
        });
    }

    public function transition(ReportingSubmission $submission, User $actor, array $data): void
    {
        abort_unless($actor->is_active,403);
        DB::transaction(function () use ($submission,$actor,$data) {
            $s = ReportingSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->authorizeView($actor,(int)$s->department_id);
            $this->validatePeriod($s->toArray());
            $action = $data['action']; $note = trim($data['note'] ?? '');
            $before = $s->status;
            if (in_array($action,['review_section','return_section'],true)) {
                $section = $s->sections()->whereKey($data['section_id'] ?? 0)->lockForUpdate()->firstOrFail();
                abort_unless($actor->isSystemAdmin() || ((int)$section->reviewer_id === $actor->id && $this->validReviewer($actor->id,(int)$s->department_id)),403);
                abort_if((int)$data['version'] !== (int)$section->version,409,'The section changed. Reload before reviewing.');
                $this->check($s->status === 'submitted_to_directorate' && $section->status === 'submitted_to_directorate','This section is not awaiting your review.');
                if ($action === 'return_section') $this->check(mb_strlen($note)>=5,'A correction comment is required.');
                if ($action === 'review_section') $this->check(!$section->entries()->whereNotIn('state',['ready','not_applicable'])->exists(),'Mark all section activities ready before approving.');
                $section->update(['status'=>$action === 'review_section' ? 'reviewed' : 'returned_to_expert','note'=>$note,'reviewed_at'=>now(),'version'=>$section->version+1]);
                if ($action === 'return_section') $section->entries()->update(['state'=>'draft','version'=>DB::raw('version + 1')]);
                if (!$s->sections()->where('status','!=','reviewed')->exists()) $s->status = 'submitted_to_chief';
            } else {
                abort_if((int)$data['version'] !== (int)$s->version,409,'The report moved to another stage. Reload before acting.');
                if ($action === 'submit') {
                    abort_unless($this->coordinator($actor,$s),403,'Only the coordinator or backup can submit this report.');
                    $this->check(in_array($s->status,['draft','submitted_to_directorate'],true),'This report is already under review.');
                    $this->check(!$s->entries()->whereNull('section_id')->exists(),'Assign a directorate reviewer to every reporting item first.');
                    $pending = $s->sections()->whereIn('status',['draft','returned_to_expert'])->get();
                    $this->check($pending->isNotEmpty(),'No draft or returned sections are available to submit.');
                    $data['notification_sections'] = $pending->pluck('id')->all();
                    foreach ($pending as $section) {
                        $this->check($this->validReviewer((int)$section->reviewer_id,(int)$s->department_id),'A reviewer is inactive or no longer belongs to this department. Ask the chief to replace them.');
                        foreach ($section->entries as $entry) {
                            $this->check(in_array($entry->state,['ready','not_applicable'],true),'Mark every required item ready or explain why it is not applicable.');
                            if ($entry->state === 'ready') {
                                $this->check($this->metricQuery($s->toArray(),[$entry->plan_item_id])->exists(),'A ready item has no actual value.');
                                $this->check(!$entry->evidence_required || count($entry->evidence_paths ?? [])>0,'Upload the required evidence before submission.');
                            }
                        }
                        $section->update(['status'=>'submitted_to_directorate','note'=>null,'version'=>$section->version+1]);
                    }
                    $s->status = 'submitted_to_directorate';
                    $s->submitted_by = $actor->id; $s->submitted_at = now();
                } else {
                    $rules = [
                        'approve_chief'=>[['submitted_to_chief','returned_to_department_chief'],'submitted_to_plan_report_department','chief'],
                        'return_directorates'=>[['submitted_to_chief','returned_to_department_chief'],'submitted_to_directorate','chief'],
                        'mark_reviewed_no_correction'=>[['submitted_to_plan_report_department','returned_to_plan_report_department'],'submitted_to_plan_report_directorate','expert'],
                        'request_correction_directorate'=>[['submitted_to_plan_report_department','returned_to_plan_report_department'],'submitted_to_plan_report_directorate','expert'],
                        'forward_plan_report'=>[['submitted_to_plan_report_department','returned_to_plan_report_department'],'submitted_to_plan_report_directorate','expert'],
                        'return_department'=>[['submitted_to_plan_report_department','returned_to_plan_report_department'],'returned_to_department_chief','expert'],
                        'return_department_directorate'=>[['submitted_to_plan_report_directorate','returned_to_plan_report_directorate'],'returned_to_department_chief','directorate'],
                        'return_plan_report'=>[['submitted_to_plan_report_directorate'],'returned_to_plan_report_department','directorate'],
                        'forward_head'=>[['submitted_to_plan_report_directorate','returned_to_plan_report_directorate'],'submitted_to_head_office','directorate'],
                        'return_directorate'=>[['submitted_to_head_office'],'returned_to_plan_report_directorate','head'],
                        'return_chief'=>[['submitted_to_head_office'],'returned_to_department_chief','head'],
                        'approve_final'=>[['submitted_to_head_office'],'approved_by_head_office','head'],
                    ];
                    $this->check(isset($rules[$action]),'Unknown reporting action.');
                    [$states,$next,$role] = $rules[$action];
                    $allowed = match ($role) {
                        'chief'=>$actor->isDepartmentChief() && (int)$actor->department_id === (int)$s->department_id,
                        'expert'=>$actor->isPlanReportDepartmentExpert(),
                        'directorate'=>$actor->isPlanReportDirectorate(),
                        'head'=>$actor->isPlanReportHeadOffice(),
                    };
                    abort_unless($actor->isSystemAdmin() || $allowed,403);
                    $this->check(in_array($s->status,$states,true),'This report is not at the required review stage.');
                    if (in_array($s->frequency,['weekly','monthly'],true)) {
                        abort_if(in_array($action,['return_department','return_department_directorate'],true),403,
                            'Weekly and monthly corrections must pass through the Plan and Report Directorate and Head Office before reaching the department chief.');
                        if ($action === 'request_correction_directorate') $this->check(mb_strlen($note)>=5,'A correction comment of at least 5 characters is required.');
                        if ($action === 'forward_head' && $note === '') $note = $s->workflow_note ?? '';
                    } else {
                        $this->check(!in_array($action,['mark_reviewed_no_correction','request_correction_directorate'],true),'This action is only available for weekly and monthly shared reports.');
                    }

                    if (str_starts_with($action,'return_')) $this->check(mb_strlen($note)>=5,'A return reason is required.');
                    if ($action === 'return_directorates') {
                        $selected = $data['sections'] ?? [];
                        $this->check(count($selected)>0,'Choose the sections requiring correction.');
                        $sections = $s->sections()->whereIn('id',$selected)->get();
                        $this->check($sections->count()===count(array_unique($selected)),'A selected section is outside this report.');
                        foreach ($sections as $section) $section->update(['status'=>'submitted_to_directorate','note'=>$note,'reviewed_at'=>null,'version'=>$section->version+1]);
                    }
                    $s->status = $next;
                    if ($action === 'approve_final') $s->approved_at = now();
                }
            }
            if ($action === 'approve_chief') {
                $this->check(!$s->entries()->whereNotIn('state',['ready','not_applicable'])->exists(),'Mark all activities ready before approving department results.');

            }
            $data['note'] = $note;
            $s->workflow_note = $note;
            $s->version++; $s->save();
            $this->synchronize($s);
            $stamp = match ($action) {
                'approve_chief','return_directorates'=>['chief_reviewed_by'=>$actor->id,'chief_reviewed_at'=>now()],
                'forward_plan_report','mark_reviewed_no_correction','request_correction_directorate'=>['plan_report_reviewed_by'=>$actor->id,'plan_report_reviewed_at'=>now()],
                'approve_final','return_chief','return_directorate'=>['head_office_reviewed_by'=>$actor->id,'head_office_reviewed_at'=>now()],
                default=>[],
            };
            if ($stamp) $this->metricQuery($s->toArray(),$s->plan->items()->forReportingFrequency($s->frequency)->pluck('id')->all())->update($stamp);
            if ($action === 'approve_chief') {
                $s->department_snapshot = $this->metricQuery($s->toArray(),$s->plan->items()->forReportingFrequency($s->frequency)->pluck('id')->all())->when($s->frequency !== 'monthly',fn ($q) => $q->with('stationData'))->get()->toArray();
                $s->department_approved_at = now();
                $s->save();
            }
            AuditLog::log('shared report '.$action,$s,['from'=>$before,'to'=>$s->status,'section'=>$data['section_id'] ?? null,'reason'=>$note,'actor'=>$actor->id]);
            DB::afterCommit(fn () => $this->notify($s,$actor,$action,$data));
        });
    }

    private function synchronize(ReportingSubmission $s): void
    {
        foreach ($s->sections()->with('entries')->get() as $section) {
            $state = $this->effectiveMetricState($s, $section);
            $updates = ['workflow_status'=>$state,'status'=>$this->metricStatus($s, $state)];
            if ($s->submitted_at) $updates += ['submitted_by'=>$s->submitted_by,'submitted_at'=>$s->submitted_at];
            if ($section->status === 'reviewed') $updates += ['directorate_reviewed_by'=>$section->reviewer_id,'directorate_reviewed_at'=>$section->reviewed_at];
            if ($state === 'approved_by_head_office') $updates['approved_at'] = $s->approved_at;
            $this->metricQuery($s->toArray(),$section->entries->pluck('plan_item_id')->all())->update($updates);
        }
        // Computed rows follow the consolidated report, never a first leaf row.
        $derived = $this->derivedIds($s);
        $this->metricQuery($s->toArray(),$derived)->update(['workflow_status'=>$s->status,'status'=>$this->metricStatus($s, $s->status)]);
    }

    private function effectiveMetricState(ReportingSubmission $submission, ?ReportingReviewSection $section): string
    {
        return $submission->status === 'submitted_to_directorate'
            ? match ($section?->status) {
                'draft' => 'draft',
                'returned_to_expert' => 'returned_to_expert',
                'reviewed' => 'submitted_to_chief',
                default => 'submitted_to_directorate',
            }
            : $submission->status;
    }

    private function metricStatus(ReportingSubmission $submission, string $state): string
    {
        // Monthly metrics retain the legacy pending/approved/rejected enum.
        // Detailed review stages belong in workflow_status.
        if ($submission->frequency === 'monthly') {
            return match (true) {
                $state === 'approved_by_head_office' => 'approved',
                str_starts_with($state, 'returned_') => 'rejected',
                default => 'pending',
            };
        }

        return $state === 'draft' || str_starts_with($state, 'returned_') ? 'draft' : 'submitted';
    }

    private function notify(ReportingSubmission $s, User $actor, string $action, array $data = []): void
    {
        $departmentRoles = fn (array $roles) => User::active()->where('department_id',$s->department_id)
            ->whereHas('roles',fn ($q) => $q->whereIn('name',$roles))->get();
        $globalRole = fn (string $role) => User::active()->whereHas('roles',fn ($q) => $q->where('name',$role))->get();
        $reviewers = fn ($sections) => User::active()->where('department_id',$s->department_id)
            ->whereIn('id',$sections->pluck('reviewer_id'))->get();
        $team = ReportingTeam::where('department_id',$s->department_id)->first();
        $experts = $departmentRoles([User::ROLE_DEPARTMENT_EXPERT]);
        $coordinators = User::active()->where('department_id',$s->department_id)
            ->whereIn('id',array_filter([$team?->coordinator_id,$team?->backup_id]))->get();
        $users = match ($action) {
            'submit' => $reviewers($s->sections()->whereIn('id',$data['notification_sections'] ?? [])->get()),
            'return_section' => $experts->merge($coordinators),
            'review_section' => $s->status === 'submitted_to_chief'
                ? $departmentRoles([User::ROLE_DEPARTMENT_CHIEF]) : $coordinators,
            'return_directorates' => $reviewers($s->sections()->whereIn('id',$data['sections'] ?? [])->get()),
            'approve_chief' => $globalRole(User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT),
            'return_department','return_department_directorate','return_chief' => $departmentRoles([User::ROLE_DEPARTMENT_CHIEF]),
            'forward_plan_report','mark_reviewed_no_correction','request_correction_directorate','return_directorate' => $globalRole(User::ROLE_PLAN_REPORT_DIRECTORATE),
            'return_plan_report' => $globalRole(User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT),
            'forward_head' => $globalRole(User::ROLE_PLAN_REPORT_HEAD_OFFICE),
            'approve_final' => $experts->merge($coordinators)
                ->merge($departmentRoles([User::ROLE_DEPARTMENT_CHIEF]))
                ->merge($reviewers($s->sections()->get()))
                ->merge($globalRole(User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT))
                ->merge($globalRole(User::ROLE_PLAN_REPORT_DIRECTORATE)),
            default => collect(),
        };
        [$title,$message,$type] = match ($action) {
            'submit' => ['Shared report submitted','Your reporting section is ready for review.','info'],
            'return_section' => ['Reporting section returned','A directorate returned a section for correction.','warning'],
            'review_section' => ['Reporting section reviewed',$s->status === 'submitted_to_chief' ? 'All sections are reviewed. Department chief approval is required.' : 'A directorate completed its section review.','success'],
            'return_directorates' => ['Shared report returned','The department chief requested corrections to your section.','warning'],
            'approve_chief' => ['Shared report approved by department','The department chief approved the report. Plan and Report review is required.','success'],
            'mark_reviewed_no_correction' => ['Shared report reviewed without correction','The Plan and Report expert reviewed the report with no correction required. Directorate confirmation is required.','success'],
            'request_correction_directorate' => ['Shared report correction requested','The Plan and Report expert requested correction through the Directorate. Forward the request to Head Office for contact with the department chief.','warning'],
            'forward_plan_report' => ['Shared report awaiting directorate','The Plan and Report expert forwarded the report for directorate review.','info'],
            'forward_head' => ['Shared report awaiting final approval','The Plan and Report directorate forwarded the report for final approval.','info'],
            'approve_final' => ['Shared report finally approved','The report received final Head Office approval.','success'],
            default => ['Shared report returned','The report was returned for correction.','warning'],
        };
        $period = ucfirst($s->frequency).' / '.$s->year.' '.ucfirst($s->month);
        if ($s->frequency !== 'monthly') $period .= ' / Week '.$s->week;
        $message = $s->department->name.' / '.$period.': '.$message;
        $note = trim($data['note'] ?? '');
        if ($note !== '') $message .= ' Comment: '.$note;
        app(WorkflowNotificationService::class)->users($users,$title,$message,route('shared-reporting.show',$s),$type,$actor);
    }

    private function persistMetric(ReportingEntry $entry, User $user, float $actual, array $data): void
    {
        $s = $entry->submission; $item = $entry->planItem;
        $state = $this->effectiveMetricState($s, $entry->section);
        if ($s->frequency === 'monthly') {
            $key = $s->year.'-'.$s->month;
            $records = $this->metricQuery($s->toArray(),[$item->id])->get();
            $record = $records->firstWhere('period',$key) ?? $records->first() ?? new PeriodicPerformance(['plan_item_id'=>$item->id]);
            $record->fill(['period'=>$key,'actual_value'=>$actual,'notes'=>$data['notes'] ?? null,'workflow_status'=>$state,'status'=>$this->metricStatus($s, $state)])->save();
            // Old entry screens used both "july" and "2026-july". Preserve the
            // previous values in the entry audit and keep just one metric row.
            PeriodicPerformance::whereIn('id',$records->pluck('id'))->where('id','!=',$record->id)->delete();
            return;
        }
        $weeks = CalendarHelper::getMonthWeeks($s->month,(int)$s->year);
        $default = $item->strategicObjective->weeklyPlanFromMonthlyTarget((float)$item->{$s->month.'_target'},count($weeks));
        $existingPlan = $this->metricQuery($s->toArray(),[$item->id])->value('planned_value');
        $plan = $existingPlan !== null ? (float)$existingPlan : $default;
        $this->check($plan >= 0,'The planned value cannot be negative.');
        $key = ['plan_item_id'=>$item->id,'year'=>$s->year,'month'=>$s->month,'week_number'=>$s->week];
        $weekly = WeeklyPerformance::updateOrCreate($key,['planned_value'=>$plan,'actual_value'=>$actual,'percentage'=>$item->strategicObjective->calculateWeeklyAchievement($actual,$plan),
            'period_start'=>$weeks[$s->week]['start_full'],'period_end'=>$weeks[$s->week]['end_full'],'due_at'=>$weeks[$s->week]['due_at'],
            'metadata'=>$data['metadata'] ?? [],'notes'=>$data['notes'] ?? null,'workflow_status'=>$state,'status'=>$this->metricStatus($s, $state),'created_by'=>$user->id]);
        if (isset($data['stations'])) {
            $weekly->stationData()->whereNotIn('station_name',array_keys($data['stations']))->delete();
            foreach ($data['stations'] as $name=>$value) $weekly->stationData()->updateOrCreate(['station_name'=>$name],['planned_value'=>0,'actual_value'=>$value]);
        }
    }

    public function metricQuery(array $s, array $items)
    {
        return $s['frequency'] === 'monthly'
            ? PeriodicPerformance::whereIn('plan_item_id',$items)->whereIn('period',[$s['month'],$s['year'].'-'.$s['month']])
            : WeeklyPerformance::whereIn('plan_item_id',$items)->where(['year'=>$s['year'],'month'=>$s['month'],'week_number'=>$s['week']]);
    }

    private function dailyKey(ReportingSubmission $s, int $item): array { return ['plan_item_id'=>$item,'year'=>$s->year,'month'=>$s->month,'week_number'=>$s->week]; }

    public function days(ReportingSubmission $s): array
    {
        $week = CalendarHelper::getMonthWeeks($s->month,(int)$s->year)[$s->week];
        return array_keys(array_filter($week['days'],fn ($day) => $day['date'] >= $week['start_full'] && $day['date'] <= $week['end_full']));
    }

    public function validReviewer(int $id, int $department): bool
    {
        $user = User::find($id);
        return $user && $user->is_active && $user->isDepartmentDirectorate() && (int)$user->department_id === $department;
    }

    private function validatePeriod(array $s, bool $saving = false): void
    {
        $this->check(!PeriodClosure::where(['department_id'=>$s['department_id'],'year'=>$s['year'],'month'=>$s['month']])->exists(),'This month is certified and locked.');
        if ($s['frequency'] !== 'monthly') {
            $week = CalendarHelper::getMonthWeeks($s['month'],(int)$s['year'])[$s['week']] ?? null;
            $this->check($week !== null,'Choose a valid reporting week.');
            if ($saving) $this->check(now()->gte(\Illuminate\Support\Carbon::parse($week['start_full'])->startOfDay()),'Future reporting weeks cannot accept data.');
        }
    }

    private function recalculate(ReportingSubmission $s, User $user): void
    {
        $derived = $this->derivedIds($s);
        $this->metricQuery($s->toArray(),$derived)->delete();
        if ($s->frequency === 'daily') DailyPerformance::whereIn('plan_item_id',$derived)->where(['year'=>$s->year,'month'=>$s->month,'week_number'=>$s->week])->delete();
        if ($s->frequency === 'monthly') app(\App\Http\Controllers\MonthlyAggregationController::class)->recalculateSharedPeriod($s);
        elseif ($s->frequency === 'daily') app(\App\Http\Controllers\DailyPerformanceController::class)->recalculateSharedPeriod($s,$user->id);
        else app(\App\Http\Controllers\WeeklyPerformanceController::class)->recalculateSharedPeriod($s,$user->id);
    }

    public function check(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['report'=>$message]);
    }

    private function derivedIds(ReportingSubmission $s): array
    {
        return $s->plan->items()->forReportingFrequency($s->frequency)
            ->whereHas('strategicObjective',fn ($q) => $q->whereHas('children')->where('summary_type','!=','manual_override'))
            ->pluck('id')->all();
    }

    public function dashboard(User $user): array
    {
        $query = ReportingSubmission::query()->when(!$user->hasOrganizationWidePerformanceAccess(),fn ($q) => $q->where('department_id',$user->department_id));
        $year = (clone $query)->max('year');
        if ($year) $query->where('year',$year);
        if ($user->isHeadOffice() && !$user->isPlanReportHeadOffice() && !$user->isSystemAdmin()) $query->where('status','approved_by_head_office');
        $reports = $query->with('sections.entries')->get();
        $isCoordinator = $user->isDepartmentExpert() && ReportingTeam::where('department_id',$user->department_id)
            ->where(fn ($q) => $q->where('coordinator_id',$user->id)->orWhere('backup_id',$user->id))->exists();
        $queue = 0; $overdue = 0;
        foreach ($reports as $s) {
            if ($user->isDepartmentDirectorate()) $queue += $s->sections->where('reviewer_id',$user->id)->where('status','submitted_to_directorate')->count();
            elseif ($isCoordinator) $queue += $s->sections->whereIn('status',['draft','returned_to_expert'])->count();
            else {
                $stages = match (true) {
                    $user->isDepartmentChief()=>['submitted_to_chief','returned_to_department_chief'],
                    $user->isPlanReportDepartmentExpert()=>['submitted_to_plan_report_department','returned_to_plan_report_department'],
                    $user->isPlanReportDirectorate()=>['submitted_to_plan_report_directorate','returned_to_plan_report_directorate'],
                    $user->isPlanReportHeadOffice()=>['submitted_to_head_office'],default=>[],
                };
                if (in_array($s->status,$stages,true)) $queue++;
            }
            if ($s->frequency !== 'monthly' && !$s->submitted_at) {
                $week = CalendarHelper::getMonthWeeks($s->month,(int)$s->year)[$s->week] ?? null;
                if ($week && now()->gt(\Illuminate\Support\Carbon::parse($week['due_at']))) $overdue++;
            }
        }
        return ['reports'=>$reports->count(),'queue'=>$queue,'overdue'=>$overdue,'returned'=>$reports->sum(fn ($s) => $s->sections->where('status','returned_to_expert')->count())];
    }

    public function notApplicableForMonth(int $department, int $year, string $month): array
    {
        $entries = ReportingEntry::where('state','not_applicable')->whereHas('submission',fn ($q) => $q->where([
            'department_id'=>$department,'year'=>$year,'month'=>$month,'status'=>'approved_by_head_office',
        ]))->with('submission')->get();
        $weeks = count(CalendarHelper::getMonthWeeks($month,$year));
        return $entries->groupBy('plan_item_id')->filter(fn ($group) => $group->contains(fn ($e) => $e->submission->frequency==='monthly')
            || $group->pluck('submission.week')->unique()->count()===$weeks)->keys()->map(fn ($id) => (int)$id)->all();
    }
}
