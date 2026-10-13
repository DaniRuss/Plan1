<?php
namespace App\Http\Controllers;

use App\Models\PlanItem;
use App\Models\PlanItemAssignment;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanAssignmentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $dept = $user->department;
        if (!$dept && ($user->isDepartmentChief() || $user->isDepartmentDirectorate() || $user->isDepartmentExpert())) {
            return back()->with('error', 'You do not have an assigned department.');
        }

        $query = PlanItemAssignment::with([
            'planItem.plan.department', 'planItem.strategicObjective.parent',
            'performer', 'assignedDirectorate', 'chiefReviewer', 'directorateReviewer',
        ])->whereHas('planItem.plan', fn ($q) => $q->where('status', 'approved'));

        if ($user->isDepartmentChief()) {
            $query->where('performer_id', $dept->id)
                ->whereIn('status', ['pending_chief_approval', 'returned_to_chief', 'pending_directorate_approval', 'approved_for_experts']);
        } elseif ($user->isDepartmentDirectorate()) {
            $query->where('assigned_directorate_user_id', $user->id);
        } elseif ($user->isDepartmentExpert()) {
            $query->where('performer_id', $dept->id)->where('status', 'approved_for_experts');
        } elseif ($user->isPlanReportHeadOffice()) {
            $query->where(function ($q) {
                $q->where('status', 'returned_to_head_office')
                    ->orWhereHas('planItem.strategicObjective', fn ($objective) => $objective->where('is_supplementary', true));
            });
        } elseif ($user->isPlanReportDepartmentExpert()) {
            $query->whereHas('planItem.strategicObjective', fn ($q) => $q->where('is_supplementary', true));
        } elseif (!$user->isSystemAdmin()) {
            $query->whereRaw('1 = 0');
        }

        $assignments = $query->get()->sort(function ($left, $right) {
            return strnatcasecmp($left->performer->name ?? '', $right->performer->name ?? '')
                ?: ($right->planItem->plan->year <=> $left->planItem->plan->year)
                ?: ($left->planItem->plan_id <=> $right->planItem->plan_id)
                ?: strnatcasecmp($left->planItem->strategicObjective->code, $right->planItem->strategicObjective->code)
                ?: ($left->id <=> $right->id);
        })->values();
        $chiefApprovalParents = [];
        $pendingAssignments = $assignments->filter(fn ($row) => in_array($row->status, ['pending_chief_approval', 'returned_to_chief'], true))
            ->keyBy(fn ($row) => $row->performer_id.':'.$row->planItem->plan_id.':'.$row->planItem->strategic_objective_id);
        foreach ($pendingAssignments as $row) {
            $parent = $row->planItem->strategicObjective->parent;
            while ($parent) {
                $ancestor = $pendingAssignments->get($row->performer_id.':'.$row->planItem->plan_id.':'.$parent->id);
                if ($ancestor) $chiefApprovalParents[$row->id] = $parent->code;
                $parent = $parent->parent;
            }
        }
        $directorates = ($user->isDepartmentChief() || ($user->isSystemAdmin() && $dept))
            ? User::where('department_id', $dept->id)->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', User::ROLE_DEPARTMENT_DIRECTORATE))
                ->orderBy('name')->get()
            : collect();

        return view('assignments.index', compact('assignments', 'directorates', 'dept', 'chiefApprovalParents'));
    }

    public function accept(PlanItemAssignment $assignment)
    {
        abort(410, 'Use the Chief and Directorate approval actions.');
    }

    public function reject(Request $request, PlanItemAssignment $assignment)
    {
        abort(410, 'Use the role-specific return action.');
    }

    public function chiefApprove(Request $request, PlanItemAssignment $assignment)
    {
        $user = auth()->user();
        $this->ensureChiefOwns($assignment, $user);
        abort_unless(in_array($assignment->status, ['pending_chief_approval', 'returned_to_chief'], true), 422, 'Assignment is not awaiting Chief action.');

        $validated = $request->validate([
            'directorate_user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('department_id', $assignment->performer_id)->where('is_active', true))],
            'note' => 'nullable|string|max:2000',
        ]);
        $directorate = User::findOrFail($validated['directorate_user_id']);
        abort_unless($directorate->isDepartmentDirectorate(), 422, 'Selected user is not a Department Directorate.');

        DB::transaction(function () use ($assignment, $directorate, $user, $validated) {
            $objectiveIds = [$assignment->planItem->strategic_objective_id];
            $frontier = $objectiveIds;
            while ($frontier) {
                $frontier = StrategicObjective::whereIn('parent_id', $frontier)
                    ->whereNotIn('id', $objectiveIds)->pluck('id')->all();
                $objectiveIds = array_merge($objectiveIds, $frontier);
            }

            PlanItemAssignment::where('performer_id', $assignment->performer_id)
                ->whereHas('planItem', fn ($q) => $q->where('plan_id', $assignment->planItem->plan_id)
                    ->whereIn('strategic_objective_id', $objectiveIds))
                ->whereIn('status', ['pending_chief_approval', 'returned_to_chief'])
                ->update([
                    'status' => 'pending_directorate_approval',
                    'assigned_directorate_user_id' => $directorate->id,
                    'chief_reviewed_by' => $user->id,
                    'chief_reviewed_at' => now(),
                    'chief_note' => $validated['note'] ?? null,
                    'rejection_reason' => null,
                ]);
        });
        app(\App\Services\WorkflowNotificationService::class)->users(
            [$directorate], 'Objective approved and assigned', $this->assignmentLabel($assignment) . ' was approved and assigned to you by the Department Chief.',
            route('assignments.index'), 'info', $user
        );
        return back()->with('success', 'Objective and its pending sub-objectives approved and assigned to Directorate.');
    }

    public function chiefReturn(Request $request, PlanItemAssignment $assignment)
    {
        $user = auth()->user();
        $this->ensureChiefOwns($assignment, $user);
        abort_unless($assignment->status === 'pending_chief_approval', 422);
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $assignment->update([
            'status' => 'returned_to_head_office', 'rejection_reason' => $validated['reason'],
            'chief_reviewed_by' => $user->id, 'chief_reviewed_at' => now(),
        ]);
        app(\App\Services\WorkflowNotificationService::class)->roles(
            User::ROLE_PLAN_REPORT_HEAD_OFFICE, 'Objective returned by Department Chief',
            $this->assignmentLabel($assignment) . ' was returned. Reason: ' . $validated['reason'], route('assignments.index'), 'warning', null, $user
        );
        return back()->with('success', 'Objective returned to Plan & Report Head Office.');
    }

    public function directorateApprove(Request $request, PlanItemAssignment $assignment)
    {
        $user = auth()->user();
        $this->ensureAssignedDirectorate($assignment, $user);
        $assignment->update([
            'status' => 'approved_for_experts', 'accepted_at' => now(),
            'directorate_reviewed_by' => $user->id, 'directorate_reviewed_at' => now(),
            'directorate_note' => $request->validate(['note' => 'nullable|string|max:2000'])['note'] ?? null,
            'rejection_reason' => null,
        ]);
        app(\App\Services\WorkflowNotificationService::class)->roles(
            [User::ROLE_DEPARTMENT_EXPERT, User::ROLE_DEPARTMENT_CHIEF], 'Objective released for reporting',
            $this->assignmentLabel($assignment) . ' was approved by Directorate and is now available for reporting.', route('assignments.index'), 'success', $assignment->performer_id, $user
        );
        if ($assignment->planItem->strategicObjective->is_supplementary) {
            app(\App\Services\WorkflowNotificationService::class)->roles(
                [User::ROLE_PLAN_REPORT_HEAD_OFFICE, User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT], 'Supplementary objective approved',
                $this->assignmentLabel($assignment) . ' is now available for monitoring.', route('assignments.index'), 'success', null, $user
            );
        }
        return back()->with('success', 'Objective approved and released to all department experts.');
    }

    public function directorateReturn(Request $request, PlanItemAssignment $assignment)
    {
        $user = auth()->user();
        $this->ensureAssignedDirectorate($assignment, $user);
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $assignment->update([
            'status' => 'returned_to_chief', 'rejection_reason' => $validated['reason'],
            'directorate_reviewed_by' => $user->id, 'directorate_reviewed_at' => now(),
        ]);
        app(\App\Services\WorkflowNotificationService::class)->roles(
            User::ROLE_DEPARTMENT_CHIEF, 'Objective returned by Directorate',
            $this->assignmentLabel($assignment) . ' was returned. Reason: ' . $validated['reason'], route('assignments.index'), 'warning', $assignment->performer_id, $user
        );
        return back()->with('success', 'Objective returned to the Department Chief.');
    }

    public function headOfficeResend(PlanItemAssignment $assignment)
    {
        $user = auth()->user();
        abort_unless($user->isPlanReportHeadOffice() || $user->isSystemAdmin(), 403);
        abort_unless($assignment->status === 'returned_to_head_office', 422);
        $assignment->update([
            'status' => 'pending_chief_approval', 'sent_by' => $user->id, 'sent_at' => now(),
            'assigned_directorate_user_id' => null, 'rejection_reason' => null,
        ]);
        app(\App\Services\WorkflowNotificationService::class)->roles(
            User::ROLE_DEPARTMENT_CHIEF, 'Objective resent for review',
            $this->assignmentLabel($assignment) . ' was resent by Plan & Report Head Office.', route('assignments.index'), 'info', $assignment->performer_id, $user
        );
        return back()->with('success', 'Objective resent to the performer Department Chief.');
    }

    public function supplementaryCreate(PlanItemAssignment $assignment)
    {
        $this->ensureChiefOwns($assignment, auth()->user());
        $assignment->load('planItem.strategicObjective', 'planItem.plan');
        return view('assignments.supplementary_create', compact('assignment'));
    }

    public function supplementaryStore(Request $request, PlanItemAssignment $assignment)
    {
        $user = auth()->user();
        $this->ensureChiefOwns($assignment, $user);
        $assignment->load('planItem.strategicObjective', 'planItem.plan');
        $parent = $assignment->planItem->strategicObjective;
        abort_if($parent->getHierarchyLevel() >= 4, 422, 'The objective hierarchy supports a maximum of four levels.');

        $validated = $request->validate([
            'description' => 'required|string|max:2000', 'unit' => 'required|string|max:100',
            'goal_direction' => 'required|in:increase,decrease', 'annual_target' => 'nullable|numeric|min:0',
            'target_distribution' => 'required|in:sum,checkpoint,manual',
            'q1_target' => 'nullable|numeric|min:0', 'q2_target' => 'nullable|numeric|min:0',
            'q3_target' => 'nullable|numeric|min:0', 'q4_target' => 'nullable|numeric|min:0',
            'july_target' => 'nullable|numeric|min:0', 'august_target' => 'nullable|numeric|min:0', 'september_target' => 'nullable|numeric|min:0',
            'october_target' => 'nullable|numeric|min:0', 'november_target' => 'nullable|numeric|min:0', 'december_target' => 'nullable|numeric|min:0',
            'january_target' => 'nullable|numeric|min:0', 'february_target' => 'nullable|numeric|min:0', 'march_target' => 'nullable|numeric|min:0',
            'april_target' => 'nullable|numeric|min:0', 'may_target' => 'nullable|numeric|min:0', 'june_target' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $assignment, $parent, $user) {
            $code = $this->nextChildCode($parent);
            $targets = $this->supplementaryTargets($validated);
            $objective = StrategicObjective::create(array_merge($validated, $targets, [
                'code' => $code, 'weight' => 0, 'parent_id' => $parent->id,
                'department_id' => $assignment->performer_id,
                'performers' => [(int) $assignment->performer_id], 'target_year' => $assignment->planItem->plan->year,
                'status' => 'approved', 'is_active' => true, 'is_supplementary' => true,
                'include_in_parent_rollup' => false, 'exclude_from_overall_score' => true,
                'is_calculated_weight' => false, 'summary_type' => 'manual_override',
            ]));
            $planItem = PlanItem::create(array_merge($targets, [
                'plan_id' => $assignment->planItem->plan_id, 'strategic_objective_id' => $objective->id,
                'annual_target' => $validated['annual_target'] ?? 0, 'weight' => 0,
                'baseline_value' => null, 'performers' => [(int) $assignment->performer_id],
            ]));
            PlanItemAssignment::create([
                'plan_item_id' => $planItem->id, 'performer_id' => $assignment->performer_id,
                'status' => 'pending_chief_approval', 'sent_by' => $user->id, 'sent_at' => now(),
            ]);
        });

        return redirect()->route('assignments.index')->with('success', 'Supplementary sub-objective created. Assign it to Directorate for approval.');
    }

    private function ensureChiefOwns(PlanItemAssignment $assignment, User $user): void
    {
        abort_unless($user->isSystemAdmin() || ($user->isDepartmentChief() && (int) $user->department_id === (int) $assignment->performer_id), 403);
    }

    private function ensureAssignedDirectorate(PlanItemAssignment $assignment, User $user): void
    {
        abort_unless($user->isSystemAdmin() || ($user->isDepartmentDirectorate()
            && (int) $assignment->assigned_directorate_user_id === (int) $user->id
            && (int) $assignment->performer_id === (int) $user->department_id), 403);
        abort_unless($assignment->status === 'pending_directorate_approval', 422);
    }

    private function nextChildCode(StrategicObjective $parent): string
    {
        $max = $parent->children()->pluck('code')->map(function ($code) {
            $parts = explode('.', trim((string) $code));
            return is_numeric(end($parts)) ? (int) end($parts) : 0;
        })->max() ?? 0;
        return $parent->code . '.' . ($max + 1);
    }

    private function supplementaryTargets(array $data): array
    {
        $quarters = [
            'q1_target' => ['july_target', 'august_target', 'september_target'],
            'q2_target' => ['october_target', 'november_target', 'december_target'],
            'q3_target' => ['january_target', 'february_target', 'march_target'],
            'q4_target' => ['april_target', 'may_target', 'june_target'],
        ];
        $result = [];
        foreach ($quarters as $quarter => $months) {
            $values = collect($months)->map(fn ($month) => (float) ($data[$month] ?? 0));
            $hasPlannedMonth = collect($months)->contains(fn ($month) => array_key_exists($month, $data) && !is_null($data[$month]));
            $result[$quarter] = match ($data['target_distribution']) {
                'sum' => $hasPlannedMonth ? $values->sum() : null,
                'manual' => $data[$quarter] ?? null,
                default => $data[$months[2]] ?? null,
            };
            foreach ($months as $month) $result[$month] = $data[$month] ?? null;
        }
        return $result;
    }

    private function assignmentLabel(PlanItemAssignment $assignment): string
    {
        $assignment->loadMissing('planItem.strategicObjective');
        $objective = $assignment->planItem->strategicObjective;
        return "Objective {$objective->code} - {$objective->description}";
    }
}
