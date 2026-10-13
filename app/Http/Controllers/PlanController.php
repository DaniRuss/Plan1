<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\StrategicObjective;
use App\Models\Department;
use App\Models\PlanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Models\AuditLog;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $query = Plan::with(['department', 'items', 'createdBy'])->orderByDesc('created_at');
        $user = auth()->user();

        if ($user->hasPermission('view_final_approved_records') && !$user->isSystemAdmin()) {
            $query->where('status', 'approved');
        } elseif (!$user->isSystemAdmin() && !$user->hasPermission('view_all_plans') && !$user->hasPermission('approve_plan')) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->has('department_id') && $request->department_id != '') {
            $query->where('department_id', $request->department_id);
        }

        $plans = $query->paginate(20);
        $departments = Department::orderBy('name')->get();

        return view('plans.index', compact('plans', 'departments'));
    }

    public function create()
    {
        $years = \App\Services\CalendarHelper::getAppYears();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // 1. Filter Departments: Only their own if they are a Chief/DeptHead
        $deptQuery = Department::orderBy('name');
        if (!$user->isSystemAdmin() && !$user->isPlanReportHeadOffice() && !$user->isHeadOffice() && $user->department_id) {
            $deptQuery->where('id', $user->department_id);
        }
        $departments = $deptQuery->get();
        // Performer names must be resolved from the complete department list.
        // The plan department selector above is intentionally scope-restricted.
        $performerDepartments = Department::where('is_active', true)->orderBy('name')->get();

        // 3. Child Departments for Dispatching (Delegating to sub-units)
        $childDepartments = collect();
        $experts = collect();
        if ($user->department_id) {
            $childDepartments = Department::where('parent_id', $user->department_id)
                ->orderBy('name')
                ->get();
            
            $childDeptIds = $childDepartments->pluck('id')->toArray();
            $targetDeptIds = array_merge([$user->department_id], $childDeptIds);

            $experts = \App\Models\User::whereHas('roles', function($q) {
                $q->where('name', \App\Models\User::ROLE_DEPARTMENT_EXPERT);
            })->whereIn('department_id', $targetDeptIds)
              ->orderBy('name')
              ->get();
        }

        // 2. Filter Objectives: Use the visibleToUser scope (which handles approval status and performers)
        $objectives = StrategicObjective::visibleToUser($user)
            ->whereNull('parent_id')
            ->with(['children' => function($q) use ($user) {
                $q->visibleToUser($user)->with(['children' => function ($activityQuery) use ($user) {
                    $activityQuery->visibleToUser($user)->orderBy('code');
                }])->orderBy('code');
            }])
            ->orderBy('code')
            ->get();

        return view('plans.create', compact('years', 'departments', 'performerDepartments', 'objectives', 'childDepartments', 'experts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer',
            'department_id' => 'required|exists:departments,id',
            'items' => 'array', // The big grid data
            // Add other validation
        ]);

        // Transaction to save Plan and PlanItems
        DB::beginTransaction();
        try {
            $plan = Plan::create([
                'title' => "Annual Plan {$validated['year']} - " . Department::find($validated['department_id'])->name,
                'type' => 'annual',
                'year' => $validated['year'],
                'department_id' => $validated['department_id'],
                'created_by' => auth()->id(),
                'status' => 'draft',
            ]);

            if (isset($request->items)) {
                foreach ($request->items as $objId => $data) {
                    // $data contains annual_target, july_target, etc.
                    if (!empty($data['annual_target'])) { // Only save if there's a target
                        $performers = array_values(array_unique(array_map('intval', $data['performers'] ?? [])));
                        $planItem = PlanItem::create([
                            'plan_id' => $plan->id,
                            'strategic_objective_id' => $objId,
                            'annual_target' => $data['annual_target'] ?? 0,
                            'performers' => $performers,
                            'experts' => array_values(array_unique(array_map('intval', $data['experts'] ?? []))),
                            'july_target' => $data['july'] ?? null,
                            'august_target' => $data['august'] ?? null,
                            'september_target' => $data['september'] ?? null,
                            'october_target' => $data['october'] ?? null,
                            'november_target' => $data['november'] ?? null,
                            'december_target' => $data['december'] ?? null,
                            'january_target' => $data['january'] ?? null,
                            'february_target' => $data['february'] ?? null,
                            'march_target' => $data['march'] ?? null,
                            'april_target' => $data['april'] ?? null,
                            'may_target' => $data['may'] ?? null,
                            'june_target' => $data['june'] ?? null,
                            'q1_target' => $data['q1_target'] ?? null,
                            'q2_target' => $data['q2_target'] ?? null,
                            'q3_target' => $data['q3_target'] ?? null,
                            'q4_target' => $data['q4_target'] ?? null,
                        ]);

                        foreach ($performers as $performerId) {
                            if ($performerId !== (int) $plan->department_id) {
                                \App\Models\PlanItemAssignment::firstOrCreate([
                                    'plan_item_id' => $planItem->id,
                                    'performer_id' => $performerId,
                                ], ['status' => 'pending']);
                            }
                        }
                    }
                }
            }

            DB::commit();
            AuditLog::log('created', $plan, "Created new plan for year {$validated['year']}");
            return redirect()->route('plans.index')->with('success', 'Plan created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating plan: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Plan $plan)
    {
        $user = auth()->user();

        if (
            !$user->isSystemAdmin()
            && !$user->hasPermission('view_all_plans')
            && !$user->hasPermission('approve_plan')
            && (int) $plan->department_id !== (int) $user->department_id
        ) {
            abort(403);
        }

        if ($user->hasPermission('view_final_approved_records') && $plan->status !== 'approved') {
            abort(403);
        }

        $plan->load(['items.strategicObjective', 'department', 'createdBy']);

        $deptId = (int)$plan->department_id;

        // Fetch hierarchical objectives associated with this specific department/plan
        $objectives = StrategicObjective::visibleToUser($user)
            ->whereNull('parent_id')
            ->where(function($q) use ($deptId) {
                $q->where('department_id', $deptId)
                  ->orWhereJsonContains('performers', $deptId);
            })
            ->with(['children' => function($q) use ($user) {
                $q->visibleToUser($user)->orderBy('code');
            }])
            ->orderBy('code')
            ->get();

        // Safety: If an objective is in the plan (has PlanItem) but was filtered out,
        // we must show its root parent anyway to ensure the data is visible.
        $planItemObjectiveIds = $plan->items->pluck('strategic_objective_id')->toArray();
        if (!empty($planItemObjectiveIds)) {
            $missingRoots = StrategicObjective::whereIn('id', $planItemObjectiveIds)
                ->get()
                ->map(function($obj) {
                    return $obj->parent_id ? $obj->getRoot() : $obj;
                })
                ->unique('id');

            foreach ($missingRoots as $root) {
                if (!$objectives->contains('id', $root->id)) {
                    $objectives->push($root);
                }
            }
            $objectives = $objectives->sortBy('code');
        }

        // 3. Child Departments for Dispatching (Delegating to sub-units)
        $childDepartments = collect();
        $experts = collect();
        if ($plan->department_id) {
            $childDepartments = Department::where('parent_id', $plan->department_id)
                ->orderBy('name')
                ->get();
            
            $childDeptIds = $childDepartments->pluck('id')->toArray();
            $targetDeptIds = array_merge([$plan->department_id], $childDeptIds);

            $experts = \App\Models\User::whereHas('roles', function($q) {
                $q->where('name', \App\Models\User::ROLE_DEPARTMENT_EXPERT);
            })->whereIn('department_id', $targetDeptIds)
              ->orderBy('name')
              ->get();
        }

        return view('plans.show', compact('plan', 'objectives', 'childDepartments', 'experts'));
    }

    public function updateItems(Request $request, Plan $plan)
    {
        // Allowed users (like Admin or the plan Creator) can update items regardless of status.
        // The policy/middleware `can:edit_plan` in web.php handles authorization.

        $validated = $request->validate([
            'items' => 'array',
        ]);

        DB::beginTransaction();
        try {
            if (isset($validated['items'])) {
                foreach ($validated['items'] as $objId => $val) {
                    // Start by checking if $val is an array (complex grid) or scalar (simple view)
                    // The current show.blade.php sends items[id] = value (scalar)
                    // The create.blade.php sends items[id] = [annual=>..., july=>...] (array)

                    $target = null;
                    $extraData = [];

                    if (is_array($val)) {
                        $target = $val['annual_target'] ?? 0;
                        $extraData = $val;
                    } else {
                        $target = $val;
                    }

                    if ($target === null && empty($extraData))
                        continue;

                    $planItem = StrategicObjective::find($objId) ? \App\Models\PlanItem::updateOrCreate(
                        [
                            'plan_id' => $plan->id,
                            'strategic_objective_id' => $objId,
                        ],
                        array_merge([
                            'annual_target' => $target,
                        ], $extraData)
                    ) : null;

                    if ($planItem) {
                        // 1. Sync Experts (Directly saved in json column)
                        $planItem->experts = $extraData['experts'] ?? [];
                        $planItem->save();

                        // 2. Sync Performers (Handled via PlanItemAssignment table)
                        $submittedPerformers = $extraData['performers'] ?? [];
                        
                        // Delete assignments that are no longer in the submitted list
                        // But ONLY if they are 'pending'. If they are already accepted, maybe we should keep them?
                        // For now, let's just delete all and recreate pending ones. 
                        // Safer: Delete those not in list.
                        \App\Models\PlanItemAssignment::where('plan_item_id', $planItem->id)
                            ->whereNotIn('performer_id', $submittedPerformers)
                            ->delete();

                        foreach ($submittedPerformers as $perfId) {
                            if ((int)$perfId !== (int)$plan->department_id) {
                                \App\Models\PlanItemAssignment::firstOrCreate([
                                    'plan_item_id' => $planItem->id,
                                    'performer_id' => $perfId
                                ]);
                            }
                        }
                    }
                }
            }
            DB::commit();
            AuditLog::log('updated', $plan, "Updated plan targets/items");
            return back()->with('success', 'Plan items updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating plan: ' . $e->getMessage());
        }
    }

    public function submit(Plan $plan)
    {
        if ($plan->status !== 'draft' && $plan->status !== 'rejected') {
            return back()->with('error', 'Plan cannot be submitted.');
        }

        $plan->update(['status' => 'submitted']);
        AuditLog::log('submitted', $plan, "Plan submitted for approval");
        return back()->with('success', 'Plan submitted for approval.');
    }

    public function approve(Plan $plan)
    {
        if (!auth()->user()->isSystemAdmin() && !auth()->user()->hasPermission('approve_plan')) {
            abort(403);
        }

        $plan->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        AuditLog::log('approved', $plan, "Plan approved by " . auth()->user()->name);
        return back()->with('success', 'Plan approved successfully.');
    }

    public function reject(Request $request, Plan $plan)
    {
        if (!auth()->user()->isSystemAdmin() && !auth()->user()->hasPermission('approve_plan')) {
            abort(403);
        }

        $validated = $request->validate([
            'rejection_comment' => 'required|string|max:1000',
        ]);

        $plan->update([
            'status' => 'rejected',
            'rejection_comment' => $validated['rejection_comment'],
        ]);

        AuditLog::log('rejected', $plan, "Plan returned to draft with comment: " . $validated['rejection_comment']);
        return back()->with('success', 'Plan returned to draft for corrections.');
    }
}
