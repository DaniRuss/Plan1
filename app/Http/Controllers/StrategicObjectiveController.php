<?php

namespace App\Http\Controllers;

use App\Models\StrategicObjective;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\ObjectivePublisher;

class StrategicObjectiveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = StrategicObjective::with(['department', 'children', 'children.children', 'children.children.children'])
            ->visibleToUser($user)
            ->whereNull('parent_id') // Show top level first
            ->orderByNaturalCode();

        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        $objectives = $query->paginate(50);
        $departments = Department::orderBy('name')->get();

        return view('objectives.index', compact('objectives', 'departments'));
    }

    public function importForm()
    {
        return view('objectives.import');
    }

    public function create(Request $request)
    {
        $parentId = $request->get('parent_id');
        
        if ($parentId && $parentId !== 'null') {
            // When creating a sub-objective for a specific parent, ONLY show that parent
            $objectives = StrategicObjective::where('id', $parentId)->get();
        } else {
            // The source action plan supports objective, KPI, activity, and detail levels.
            $objectives = StrategicObjective::with('parent')->orderByNaturalCode()->get()
                ->filter(fn ($objective) => $objective->getHierarchyLevel() < 4);
        }

        $departments = Department::whereNull('parent_id')->orderBy('name')->get();

        $parent = null;
        $parentId = $request->get('parent_id');
        
        // Use the same logic as the API for consistency
        $codeResponse = $this->getNextCode($parentId);
        $suggestedCode = $codeResponse->getData()->code;

        if ($parentId) {
            $parent = StrategicObjective::with('parent')->find($parentId);
        }

        $defaultPerformerIds = array_values(array_unique(array_map('intval', $parent?->performers ?? [])));
        $defaultLeadDepartmentId = $parent?->lead_department_id ?: ($defaultPerformerIds[0] ?? null);

        $years = \App\Services\CalendarHelper::getAppYears();
        return view('objectives.create', compact('objectives', 'departments', 'parent', 'suggestedCode', 'years', 'defaultPerformerIds', 'defaultLeadDepartmentId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:strategic_objectives',
            'kpi_code' => 'nullable|string|max:50',
            'description' => 'required|string',
            'weight' => 'required_unless:is_calculated_weight,1|nullable|numeric|min:0',
            'unit' => 'required|string',
            'baseline_value' => 'nullable|numeric',
            'baseline_year' => 'nullable|string',
            'annual_target' => 'nullable|numeric',
            'target_distribution' => 'nullable|string|in:sum,checkpoint,manual',
            'weekly_plan_mode' => 'nullable|string|in:distribute,repeat',
            'reporting_frequency' => 'nullable|in:daily,weekly,monthly',
            'july_target' => 'nullable|numeric', 'august_target' => 'nullable|numeric',
            'september_target' => 'nullable|numeric', 'q1_target' => 'nullable|numeric',
            'october_target' => 'nullable|numeric', 'november_target' => 'nullable|numeric',
            'december_target' => 'nullable|numeric', 'q2_target' => 'nullable|numeric',
            'january_target' => 'nullable|numeric', 'february_target' => 'nullable|numeric',
            'march_target' => 'nullable|numeric', 'q3_target' => 'nullable|numeric',
            'april_target' => 'nullable|numeric', 'may_target' => 'nullable|numeric',
            'june_target' => 'nullable|numeric', 'q4_target' => 'nullable|numeric',
            'target_year' => 'required|integer',
            'parent_id' => 'nullable|exists:strategic_objectives,id',
            'performers' => 'nullable|array',
            'performers.*' => 'exists:departments,id',
            'lead_department_id' => 'nullable|exists:departments,id',
            'company_consolidation_method' => 'nullable|string|in:lead_department',
            'template_type' => 'nullable|string|in:standard,machinery,truck_haulage,revenue',
            'summary_type' => 'nullable|string|in:weighted,sum,average,manual_override',
            'data_source' => 'nullable|string|max:255',
            'goal_direction' => 'required|string|in:increase,decrease',
            'is_reference_only' => 'nullable|boolean',
            'is_calculated_weight' => 'nullable|boolean',
            'include_in_parent_rollup' => 'nullable|boolean',
        ]);

        // The objective is owned by the creator's department
        if (!empty($validated['reporting_frequency'])) abort_unless(auth()->user()->canManageObjectiveFrequency(), 403);
        $validated['department_id'] = auth()->user() ? auth()->user()->department_id : null;
        if (!empty($validated['parent_id']) && empty($validated['lead_department_id']) && empty($validated['performers'])) {
            $performerParent = StrategicObjective::find($validated['parent_id']);
            $validated['performers'] = $performerParent?->performers ?? [];
            $validated['lead_department_id'] = $performerParent?->lead_department_id;
        }
        $validated['performers'] = array_values(array_unique(array_map('intval', $validated['performers'] ?? [])));
        $validated['lead_department_id'] = (int) ($validated['lead_department_id'] ?? ($validated['performers'][0] ?? 0));
        if (!$validated['lead_department_id']) {
            throw ValidationException::withMessages(['lead_department_id' => 'Select a lead/accountable department.']);
        }
        if (!in_array($validated['lead_department_id'], $validated['performers'], true)) {
            array_unshift($validated['performers'], $validated['lead_department_id']);
        }
        $validated['company_consolidation_method'] = 'lead_department';
        $validated['include_in_parent_rollup'] = empty($validated['parent_id'])
            ? true
            : $request->boolean('include_in_parent_rollup');
        // A calculated parent starts at zero and is updated every time one of
        // its direct sub-objectives are created, edited, moved, or deleted.
        $validated['weight'] = !empty($validated['is_calculated_weight'])
            ? 0
            : (float) $validated['weight'];
        $validated['target_distribution'] = $validated['target_distribution'] ?? 'checkpoint';
        $validated['weekly_plan_mode'] = $validated['weekly_plan_mode'] ?? 'distribute';
        $validated = $this->calculateQuarterTargets($validated);

        // Dynamic validation: If unit is Percentage (%), annual target must be <= 100
        if (in_array($validated['unit'], ['Percentage (%)', '%'], true) && isset($validated['annual_target']) && $validated['annual_target'] > 100) {
            throw ValidationException::withMessages([
                'annual_target' => 'Annual target for percentage-based objectives cannot exceed 100%.'
            ]);
        }

        // STRICT WEIGHT VALIDATION: Child sum must not exceed Parent Weight
        if (!empty($validated['parent_id'])) {
            $parent = StrategicObjective::find($validated['parent_id']);

            if ($parent->getHierarchyLevel() >= 4) {
                throw ValidationException::withMessages(['parent_id' => 'The action-plan hierarchy supports a maximum of four levels.']);
            }

            // Check if adding this weight exceeds parent's remaining weight
            // Using the helper method we added to the Model
            if (!$parent->is_calculated_weight && !$parent->canAllocateWeight($validated['weight'])) {
                throw ValidationException::withMessages([
                    'weight' => "Cannot allocate {$validated['weight']}%. Only {$parent->getRemainingWeight()}% remaining in main objective '{$parent->code}'."
                ]);
            }
        }

        $validated['is_active'] = true;
        $validated['status'] = 'draft';
        $validated['owner_id'] = auth()->id();

        $objective = StrategicObjective::create($validated);
        $objective->syncCalculatedWeight();
        $objective->parent?->syncCalculatedWeight();

        return redirect()->route('objectives.index')->with('success', 'Strategic Objective created successfully.');
    }

    public function show(StrategicObjective $objective)
    {
        abort_unless(
            StrategicObjective::query()->visibleToUser(auth()->user())->whereKey($objective->id)->exists(),
            403,
            'Unauthorized access to this objective.'
        );

        $objective->load(['department', 'leadDepartment', 'parent', 'children', 'lastEditor']);
        $performingDepartments = Department::whereIn('id', $objective->performers ?? [])->orderBy('name')->get(['id', 'name']);
        $revisionHistory = \App\Models\AuditLog::with('user')
            ->where('model_type', StrategicObjective::class)
            ->where('model_id', $objective->id)
            ->where('action', 'strategic_objective_updated')
            ->latest()
            ->get()
            ->map(function ($log) {
                $details = json_decode($log->details ?? '', true);
                $log->revision_details = is_array($details) ? $details : [];

                return $log;
            });

        return view('objectives.show', compact('objective', 'revisionHistory', 'performingDepartments'));
    }

    public function edit(StrategicObjective $objective)
    {
        abort_unless(
            $this->canEditObjective(auth()->user(), $objective),
            403,
            'You cannot edit this objective at its current review stage.'
        );
        $excludedIds = $objective->children()->pluck('id')->push($objective->id);
        $objectives = StrategicObjective::with('parent')->whereNotIn('id', $excludedIds)
            ->orderByNaturalCode()->get()->filter(fn ($candidate) => $candidate->getHierarchyLevel() < 4);
            
        $departments = Department::whereNull('parent_id')->orderBy('name')->get();

        $remainingWeight = null;
        if ($objective->parent) {
            // Remaining in parent + current weight of this objective
            $remainingWeight = $objective->parent->getRemainingWeight() + $objective->weight;
        }

        $years = \App\Services\CalendarHelper::getAppYears();
        return view('objectives.edit', compact('objective', 'objectives', 'departments', 'remainingWeight', 'years'));
    }

    public function update(Request $request, StrategicObjective $objective)
    {
        $user = auth()->user();
        abort_unless(
            $this->canEditObjective($user, $objective),
            403,
            'You cannot edit this objective at its current review stage.'
        );
        $isReviewerEdit = ($user->isPlanReportDirectorate() && $objective->status === 'submitted_to_plan_report_directorate')
            || ($user->isPlanReportHeadOffice() && $objective->status === 'submitted');
        $changeReason = $isReviewerEdit
            ? $request->validate(['review_change_reason' => 'required|string|min:5|max:2000'])['review_change_reason']
            : null;
        $trackedFields = [
            'code', 'kpi_code', 'description', 'weight', 'unit', 'baseline_value', 'baseline_year',
            'annual_target', 'target_distribution', 'weekly_plan_mode', 'july_target', 'august_target',
            'september_target', 'q1_target', 'october_target', 'november_target', 'december_target',
            'q2_target', 'january_target', 'february_target', 'march_target', 'q3_target', 'april_target',
            'may_target', 'june_target', 'q4_target', 'target_year', 'parent_id', 'performers',
            'lead_department_id', 'is_active', 'summary_type', 'data_source', 'goal_direction',
            'is_reference_only', 'is_calculated_weight', 'include_in_parent_rollup',
            'reporting_frequency',
        ];
        $before = $objective->only($trackedFields);
        $validated = $request->validate([
            'code' => 'required|string|unique:strategic_objectives,code,' . $objective->id,
            'kpi_code' => 'nullable|string|max:50',
            'description' => 'required|string',
            'weight' => 'required_unless:is_calculated_weight,1|nullable|numeric|min:0',
            'unit' => 'required|string',
            'baseline_value' => 'nullable|numeric',
            'baseline_year' => 'nullable|string',
            'annual_target' => 'nullable|numeric',
            'target_distribution' => 'nullable|string|in:sum,checkpoint,manual',
            'weekly_plan_mode' => 'nullable|string|in:distribute,repeat',
            'reporting_frequency' => 'nullable|in:daily,weekly,monthly',
            'july_target' => 'nullable|numeric', 'august_target' => 'nullable|numeric',
            'september_target' => 'nullable|numeric', 'q1_target' => 'nullable|numeric',
            'october_target' => 'nullable|numeric', 'november_target' => 'nullable|numeric',
            'december_target' => 'nullable|numeric', 'q2_target' => 'nullable|numeric',
            'january_target' => 'nullable|numeric', 'february_target' => 'nullable|numeric',
            'march_target' => 'nullable|numeric', 'q3_target' => 'nullable|numeric',
            'april_target' => 'nullable|numeric', 'may_target' => 'nullable|numeric',
            'june_target' => 'nullable|numeric', 'q4_target' => 'nullable|numeric',
            'target_year' => 'required|integer',
            'parent_id' => 'nullable|exists:strategic_objectives,id',
            'performers' => 'nullable|array',
            'performers.*' => 'exists:departments,id',
            'lead_department_id' => 'nullable|exists:departments,id',
            'company_consolidation_method' => 'nullable|string|in:lead_department',
            'is_active' => 'boolean',
            'template_type' => 'nullable|string|in:standard,machinery,truck_haulage,revenue',
            'summary_type' => 'nullable|string|in:weighted,sum,average,manual_override',
            'data_source' => 'nullable|string|max:255',
            'goal_direction' => 'required|string|in:increase,decrease',
            'is_reference_only' => 'nullable|boolean',
            'is_calculated_weight' => 'nullable|boolean',
            'include_in_parent_rollup' => 'nullable|boolean',
        ]);

        // Reviewers may correct submitted objectives, but ownership must remain
        if (array_key_exists('reporting_frequency', $validated) && $validated['reporting_frequency'] !== $objective->reporting_frequency) {
            abort_unless($user->canManageObjectiveFrequency(), 403);
            if ($objective->status === 'approved') {
                throw ValidationException::withMessages(['reporting_frequency' => 'Request a frequency change from Objective Details so it can be reapproved.']);
            }
        }
        // with the department that originally prepared the objective.
        $validated['department_id'] = $objective->department_id;
        $validated['revision_number'] = ((int) $objective->revision_number) + 1;
        $validated['last_edited_by'] = $user->id;
        $validated['last_edited_at'] = now();
        $validated['last_edited_stage'] = $objective->status;
        $validated['last_change_reason'] = $changeReason;
        $validated['performers'] = array_values(array_unique(array_map('intval', $validated['performers'] ?? [])));
        $validated['lead_department_id'] = (int) ($validated['lead_department_id'] ?? ($validated['performers'][0] ?? 0));
        if (!$validated['lead_department_id']) {
            throw ValidationException::withMessages(['lead_department_id' => 'Select a lead/accountable department.']);
        }
        if (!in_array($validated['lead_department_id'], $validated['performers'], true)) {
            array_unshift($validated['performers'], $validated['lead_department_id']);
        }
        $validated['company_consolidation_method'] = 'lead_department';
        $validated['include_in_parent_rollup'] = empty($validated['parent_id'])
            ? true
            : $request->boolean('include_in_parent_rollup');
        $validated['weight'] = !empty($validated['is_calculated_weight'])
            ? (float) $objective->children()->sum('weight')
            : (float) $validated['weight'];
        $validated['target_distribution'] = $validated['target_distribution'] ?? ($objective->target_distribution ?: 'checkpoint');
        $validated['weekly_plan_mode'] = $validated['weekly_plan_mode'] ?? ($objective->weekly_plan_mode ?: 'distribute');
        $validated = $this->calculateQuarterTargets($validated);

        // Dynamic validation: If unit is Percentage (%), annual target must be <= 100
        if (in_array($validated['unit'], ['Percentage (%)', '%'], true) && isset($validated['annual_target']) && $validated['annual_target'] > 100) {
            throw ValidationException::withMessages([
                'annual_target' => 'Annual target for percentage-based objectives cannot exceed 100%.'
            ]);
        }

        // STRICT WEIGHT VALIDATION
        if (!empty($validated['parent_id'])) {
            $parent = StrategicObjective::find($validated['parent_id']);

            if ($parent->getHierarchyLevel() >= 4) {
                throw ValidationException::withMessages(['parent_id' => 'The action-plan hierarchy supports a maximum of four levels.']);
            }

            // Check allocation, ignoring self (since we are updating)
            if (!$parent->is_calculated_weight && !$parent->canAllocateWeight($validated['weight'], $objective->id)) {
                $remaining = $parent->weight - $parent->children()->where('id', '!=', $objective->id)->sum('weight');
                throw ValidationException::withMessages([
                    'weight' => "Cannot allocate {$validated['weight']}%. Only {$remaining}% available in main objective '{$parent->code}'."
                ]);
            }
        }

        $oldParent = $objective->parent;
        $objective->update($validated);
        $objective->syncCalculatedWeight();
        $oldParent?->syncCalculatedWeight();
        $objective->fresh()->parent?->syncCalculatedWeight();

        $objective->refresh();
        $after = $objective->only($trackedFields);
        $changes = collect($trackedFields)->mapWithKeys(function ($field) use ($before, $after) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            return $old != $new ? [$field => ['before' => $old, 'after' => $new]] : [];
        })->all();
        \App\Models\AuditLog::log(
            'strategic_objective_updated',
            $objective,
            [
                'revision' => $objective->revision_number,
                'stage' => $objective->status,
                'editor' => ['id' => $user->id, 'name' => $user->name, 'department_id' => $user->department_id],
                'reason' => $changeReason,
                'changes' => $changes,
            ]
        );

        if ($isReviewerEdit) {
            $message = "Objective {$objective->code} was revised by {$user->name}. Reason: {$changeReason}";
            $owner = $objective->owner_id ? \App\Models\User::find($objective->owner_id) : null;
            if ($owner) {
                app(\App\Services\WorkflowNotificationService::class)->users(
                    [$owner], 'Submitted objective revised', $message,
                    route('objectives.show', $objective), 'warning', $user
                );
            } else {
                app(\App\Services\WorkflowNotificationService::class)->roles(
                    \App\Models\User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT,
                    'Submitted objective revised', $message,
                    route('objectives.show', $objective), 'warning', null, $user
                );
            }
        }

        return redirect()->route('objectives.index')->with('success', 'Strategic Objective updated successfully.');
    }

    private function canEditObjective(\App\Models\User $user, StrategicObjective $objective): bool
    {
        if ($user->isSystemAdmin()) {
            return true;
        }

        if ($user->hasPermission('manage_objectives') && in_array($objective->status, ['draft', 'returned'], true)) {
            return true;
        }

        if ($user->isPlanReportDirectorate() && $objective->status === 'submitted_to_plan_report_directorate') {
            return true;
        }

        return $user->isPlanReportHeadOffice() && $objective->status === 'submitted';
    }

    public function destroy(StrategicObjective $objective)
    {
        abort_unless(
            auth()->user()->isSystemAdmin() || in_array($objective->status, ['draft', 'returned'], true),
            423,
            'Submitted objectives must be recalled and approved before they can be deleted.'
        );
        if ($objective->children()->exists()) {
            return back()->with('error', 'Cannot delete objective with sub-objectives.');
        }

        $parent = $objective->parent;
        $objective->delete();
        $parent?->syncCalculatedWeight();
        return redirect()->route('objectives.index')->with('success', 'Strategic Objective deleted successfully.');
    }

    public function submit(StrategicObjective $objective)
    {
        if ($objective->status !== 'draft' && $objective->status !== 'returned') {
            return back()->with('error', 'Objective cannot be submitted.');
        }

        $this->updateStatusRecursively($objective, 'submitted_to_plan_report_directorate', null);
        app(\App\Services\WorkflowNotificationService::class)->roles(
            [\App\Models\User::ROLE_PLAN_REPORT_DIRECTORATE], 'Objective submitted for directorate review',
            "Objective {$objective->code} - {$objective->description} is awaiting directorate review.", route('objectives.show', $objective), 'info', null, auth()->user()
        );
        return back()->with('success', 'Objective and its sub-activities submitted to the Plan and Report Directorate.');
    }

    public function directorateForward(StrategicObjective $objective)
    {
        abort_unless(auth()->user()->isPlanReportDirectorate() || auth()->user()->isSystemAdmin(), 403);
        abort_unless($objective->status === 'submitted_to_plan_report_directorate', 422, 'Objective is not awaiting directorate review.');
        $this->validateObjectiveTreeForReview($objective->getRoot());

        $this->updateStatusRecursively($objective, 'submitted', null);
        app(\App\Services\WorkflowNotificationService::class)->roles(
            [\App\Models\User::ROLE_PLAN_REPORT_HEAD_OFFICE], 'Objective submitted for final approval',
            "Objective {$objective->code} - {$objective->description} was reviewed by the Directorate.", route('objectives.show', $objective), 'info', null, auth()->user()
        );
        return back()->with('success', 'Objective reviewed and sent to the Head of Plan and Report.');
    }

    public function directorateReturn(Request $request, StrategicObjective $objective)
    {
        abort_unless(auth()->user()->isPlanReportDirectorate() || auth()->user()->isSystemAdmin(), 403);
        abort_unless($objective->status === 'submitted_to_plan_report_directorate', 422, 'Objective is not awaiting directorate review.');
        $validated = $request->validate(['rejection_comment' => 'required|string|max:2000']);
        $this->updateStatusRecursively($objective, 'returned', $validated['rejection_comment']);
        return back()->with('success', 'Objective returned to the Plan and Report Expert for correction.');
    }

    public function requestRecall(Request $request, StrategicObjective $objective)
    {
        $user = auth()->user();
        abort_unless($user->isPlanReportDepartmentExpert() || $user->isSystemAdmin(), 403);
        $root = $objective->getRoot();
        abort_unless(in_array($root->status, ['submitted_to_plan_report_directorate', 'submitted'], true), 422, 'Only objectives awaiting Directorate or Head approval can be recalled.');
        $validated = $request->validate(['recall_reason' => 'required|string|max:2000']);

        $previousStatus = $root->status;
        $requestStatus = $previousStatus === 'submitted'
            ? 'recall_requested_from_head_office'
            : 'recall_requested_from_plan_report_directorate';

        \Illuminate\Support\Facades\DB::transaction(function () use ($root, $user, $validated, $previousStatus, $requestStatus) {
            $this->updateTree($root, [
                'status' => $requestStatus,
                'recall_requested_by' => $user->id,
                'recall_requested_at' => now(),
                'recall_reason' => $validated['recall_reason'],
                'recall_from_status' => $previousStatus,
            ]);
        });

        $role = $previousStatus === 'submitted'
            ? \App\Models\User::ROLE_PLAN_REPORT_HEAD_OFFICE
            : \App\Models\User::ROLE_PLAN_REPORT_DIRECTORATE;
        app(\App\Services\WorkflowNotificationService::class)->roles(
            [$role], 'Objective recall approval requested',
            "Recall requested for objective {$root->code}. Reason: {$validated['recall_reason']}",
            route('objectives.index'), 'warning', null, $user
        );
        \App\Models\AuditLog::log('objective_recall_requested', $root, $validated['recall_reason']);

        return back()->with('success', 'Recall request sent to the current reviewer for approval.');
    }

    public function approveRecall(StrategicObjective $objective)
    {
        $root = $objective->getRoot();
        $user = auth()->user();
        $waitingForHead = $root->status === 'recall_requested_from_head_office';
        $waitingForDirectorate = $root->status === 'recall_requested_from_plan_report_directorate';
        abort_unless(
            $user->isSystemAdmin()
            || ($waitingForHead && $user->isPlanReportHeadOffice())
            || ($waitingForDirectorate && $user->isPlanReportDirectorate()),
            403
        );
        abort_unless($waitingForHead || $waitingForDirectorate, 422, 'No recall request is awaiting approval.');

        $reason = $root->recall_reason;
        \Illuminate\Support\Facades\DB::transaction(function () use ($root, $reason) {
            $this->updateTree($root, [
                'status' => 'returned',
                'rejection_comment' => 'Recall approved: ' . $reason,
                'recall_requested_by' => null,
                'recall_requested_at' => null,
                'recall_reason' => null,
                'recall_from_status' => null,
            ]);
        });
        \App\Models\AuditLog::log('objective_recall_approved', $root, $reason);
        return back()->with('success', 'Recall approved. The objective tree is editable and must be resubmitted.');
    }

    public function rejectRecall(Request $request, StrategicObjective $objective)
    {
        $root = $objective->getRoot();
        $user = auth()->user();
        $waitingForHead = $root->status === 'recall_requested_from_head_office';
        $waitingForDirectorate = $root->status === 'recall_requested_from_plan_report_directorate';
        abort_unless(
            $user->isSystemAdmin()
            || ($waitingForHead && $user->isPlanReportHeadOffice())
            || ($waitingForDirectorate && $user->isPlanReportDirectorate()),
            403
        );
        abort_unless($waitingForHead || $waitingForDirectorate, 422, 'No recall request is awaiting a decision.');
        $validated = $request->validate(['decision_reason' => 'required|string|max:2000']);
        $restoreStatus = $root->recall_from_status;

        \Illuminate\Support\Facades\DB::transaction(function () use ($root, $restoreStatus) {
            $this->updateTree($root, [
                'status' => $restoreStatus,
                'recall_requested_by' => null,
                'recall_requested_at' => null,
                'recall_reason' => null,
                'recall_from_status' => null,
            ]);
        });
        \App\Models\AuditLog::log('objective_recall_rejected', $root, $validated['decision_reason']);
        return back()->with('success', 'Recall rejected. The objective remains with the current reviewer.');
    }

    private function updateTree(StrategicObjective $objective, array $values): void
    {
        $objective->update($values);
        foreach ($objective->children()->get() as $child) {
            $this->updateTree($child, $values);
        }
    }

    public function approve(StrategicObjective $objective, ObjectivePublisher $publisher)
    {
        if (!auth()->user()->isPlanReportHeadOffice() && !auth()->user()->isSystemAdmin()) {
            abort(403);
        }
        abort_unless(
            $objective->status === 'submitted' || auth()->user()->isSystemAdmin(),
            422,
            'Objective is not awaiting final approval.'
        );
        $this->validateObjectiveTreeForReview($objective->getRoot());

        \Illuminate\Support\Facades\DB::transaction(function () use ($objective, $publisher) {
            $publisher->publishTree($objective, auth()->user());
            $this->updateStatusRecursively($objective, 'approved', null);
        });
        $departmentIds = $objective->fresh()->planItems()->with('assignments')->get()
            ->flatMap->assignments->pluck('performer_id')->unique();
        foreach ($departmentIds as $departmentId) {
            app(\App\Services\WorkflowNotificationService::class)->roles(
                \App\Models\User::ROLE_DEPARTMENT_CHIEF, 'Approved objective received',
                "Objective {$objective->code} - {$objective->description} was approved and requires your review.", route('assignments.index'), 'info', (int) $departmentId, auth()->user()
            );
        }
        return back()->with('success', 'Objective approved and published directly to performer reporting workflows.');
    }

    public function reject(Request $request, StrategicObjective $objective)
    {
        if (!auth()->user()->isPlanReportHeadOffice() && !auth()->user()->isSystemAdmin()) {
            abort(403);
        }

        $request->validate(['rejection_comment' => 'required|string']);

        $this->updateStatusRecursively($objective, 'returned', $request->rejection_comment);
        if ($objective->owner_id) {
            app(\App\Services\WorkflowNotificationService::class)->users(
                [\App\Models\User::find($objective->owner_id)], 'Objective returned for revision',
                "Objective {$objective->code} was returned. Reason: {$request->rejection_comment}", route('objectives.edit', $objective), 'warning', auth()->user()
            );
        }
        return back()->with('success', 'Objective and its sub-activities returned for revision.');
    }

    private function validateObjectiveTreeForReview(StrategicObjective $root): void
    {
        $errors = [];
        $walk = function (StrategicObjective $node) use (&$walk, &$errors): void {
            $node->loadMissing('children');
            $performers = array_map('intval', $node->performers ?? []);

            if (blank($node->code) || blank($node->description) || blank($node->unit)) {
                $errors[] = "Objective {$node->id} is missing its code, description, or unit.";
            }
            if (!$performers) {
                $errors[] = "Objective {$node->code} has no performing department.";
            }
            if (!$node->lead_department_id && $performers) {
                // Compatibility for objectives created before lead-department
                // tracking: use the first assigned performer deterministically.
                $node->forceFill(['lead_department_id' => $performers[0]])->saveQuietly();
            } elseif ($node->lead_department_id && !in_array((int) $node->lead_department_id, $performers, true)) {
                $errors[] = "Objective {$node->code} must have a lead department included among its performers.";
            }
            if (in_array($node->unit, ['Percentage (%)', '%'], true) && (float) $node->annual_target > 100) {
                $errors[] = "Objective {$node->code} has a percentage target above 100%.";
            }
            if ($node->children->isNotEmpty() && !$node->is_calculated_weight) {
                $allocated = (float) $node->children->sum('weight');
                if ($allocated - (float) $node->weight > 0.0001) {
                    $errors[] = "Sub-objectives of main objective {$node->code} exceed its available weight.";
                }
            }

            foreach ($node->children as $child) {
                $walk($child);
            }
        };
        $walk($root);

        if ($errors) {
            throw ValidationException::withMessages([
                'objective' => 'The objective tree cannot advance: '.implode(' ', $errors),
            ]);
        }
    }

    private function updateStatusRecursively(StrategicObjective $objective, $status, $comment = null)
    {
        $objective->update([
            'status' => $status,
            'rejection_comment' => $comment
        ]);
        
        foreach ($objective->children as $child) {
            $this->updateStatusRecursively($child, $status, $comment);
        }
    }

    private function calculateQuarterTargets(array $data): array
    {
        if (($data['target_distribution'] ?? 'checkpoint') === 'manual') {
            return $data;
        }

        $groups = [
            'q1_target' => ['july_target', 'august_target', 'september_target'],
            'q2_target' => ['october_target', 'november_target', 'december_target'],
            'q3_target' => ['january_target', 'february_target', 'march_target'],
            'q4_target' => ['april_target', 'may_target', 'june_target'],
        ];

        foreach ($groups as $quarter => $months) {
            if ($data['target_distribution'] === 'sum') {
                $hasPlannedMonth = collect($months)->contains(fn ($month) => array_key_exists($month, $data) && !is_null($data[$month]));
                $data[$quarter] = $hasPlannedMonth
                    ? array_sum(array_map(fn ($month) => (float) ($data[$month] ?? 0), $months))
                    : null;
            } else {
                $data[$quarter] = $data[$months[2]] ?? null;
            }
        }

        return $data;
    }

    public function downloadTemplate()
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Objectives');
        $headers = ['S.No', 'KPIs', 'Weight', 'Unit', 'Baseline', 'Annual Target'];
        foreach (\App\Services\ObjectiveWorkbookImport::PERIOD_COLUMNS as $field => $column) {
            $headers[] = ucwords(str_replace('_', ' ', $field));
        }
        $headers[] = 'Performer';
        $headers[] = 'Reporting Frequency';
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['1', 'Example main objective', 5, 'Tons', null, 100], null, 'A2');
        $sheet->fromArray(['1.1', 'Example weekly sub-objective', 3, 'Tons', null, 60], null, 'A3');
        $sheet->fromArray(['1.2', 'Example monthly sub-objective', 2, 'Tons', null, 40], null, 'A4');
        $sheet->setCellValue('X2', 'INHERIT')->setCellValue('X3', 'INHERIT')->setCellValue('X4', 'MONTHLY');
        $sheet->getStyle('A1:X1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:X1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF173B54');
        $sheet->getStyle('A1:X4')->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(42);
        foreach (range('C', 'V') as $column) $sheet->getColumnDimension($column)->setWidth(16);
        $sheet->getColumnDimension('W')->setWidth(30);
        $sheet->getColumnDimension('X')->setWidth(26);
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane('C2');
        $validation = $sheet->getCell('X2')->getDataValidation();
        $validation->setType('list')->setErrorStyle('stop')->setAllowBlank(true)
            ->setShowDropDown(true)->setShowErrorMessage(true)
            ->setFormula1('"INHERIT,DAILY,WEEKLY,MONTHLY"');
        $validation->setSqref('X2:X1000');
        return response()->streamDownload(function () use ($book) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, 'strategic_objectives_template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function getNextCode($parentId = null)
    {
        $code = '';
        if ($parentId && $parentId !== 'null') {
            $parent = StrategicObjective::findOrFail($parentId);
            $parentCode = $parent->code;
            
            // Calculate in PHP so code generation works consistently on both
            // MySQL (production) and SQLite (automated tests).
            $lastNumber = StrategicObjective::where('parent_id', $parentId)
                ->pluck('code')->map(function ($childCode) {
                    $parts = explode('.', $childCode);
                    $last = end($parts);
                    return ctype_digit((string) $last) ? (int) $last : 0;
                })->max() ?? 0;
            $nextNum = $lastNumber + 1;
            $code = $parentCode . '.' . $nextNum;
        } else {
            // Top level
            $lastTopLevel = StrategicObjective::whereNull('parent_id')->pluck('code')
                ->filter(fn ($topCode) => ctype_digit((string) $topCode))
                ->map(fn ($topCode) => (int) $topCode)->max() ?? 0;
            $nextNum = $lastTopLevel + 1;
            $code = (string) $nextNum;
        }

        return response()->json(['code' => $code]);
    }
}
