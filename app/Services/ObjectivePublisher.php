<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PlanItemAssignment;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ObjectivePublisher
{
    /**
     * Materialize approved objectives into the existing reporting schema.
     * Plans remain an internal compatibility container; users no longer prepare
     * them separately after completing objective targets and performers.
     */
    public function publishTree(StrategicObjective $objective, User $approver): void
    {
        DB::transaction(function () use ($objective, $approver) {
            $this->publishObjective($objective->fresh(), $approver);

            foreach ($objective->children()->get() as $child) {
                $this->publishTree($child, $approver);
            }
        });
    }

    private function publishObjective(StrategicObjective $objective, User $approver): void
    {
        $year = filter_var($objective->target_year, FILTER_VALIDATE_INT);
        if (!$year) {
            throw ValidationException::withMessages([
                'target_year' => "Objective {$objective->code} must have a performance year before approval.",
            ]);
        }

        $performerIds = collect($objective->performers ?? [])->map(fn ($id) => (int) $id)
            ->filter()->unique()->values();
        if ($performerIds->isEmpty()) {
            throw ValidationException::withMessages([
                'performers' => "Objective {$objective->code} must have at least one performer department before approval.",
            ]);
        }

        $departments = Department::whereIn('id', $performerIds)->get()->keyBy('id');
        foreach ($performerIds as $departmentId) {
            $department = $departments->get($departmentId);
            if (!$department) continue;

            $plan = Plan::firstOrCreate(
                ['year' => (int) $year, 'department_id' => $departmentId, 'type' => 'annual'],
                [
                    'title' => "Approved Objectives {$year} - {$department->name}",
                    'created_by' => $objective->owner_id ?: $approver->id,
                    'status' => 'approved',
                    'approved_by' => $approver->id,
                    'approved_at' => now(),
                ]
            );

            if ($plan->status !== 'approved') {
                $plan->update([
                    'status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now(),
                ]);
            }

            $planItem = PlanItem::updateOrCreate(
                ['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id],
                [
                    'annual_target' => $objective->annual_target ?? 0,
                    'weight' => $objective->weight,
                    // plan_items retains a legacy normalized enum; detailed display
                    // and scoring continue to use the objective's full unit label.
                    'unit' => $this->legacyPlanUnit($objective->unit),
                    'baseline_value' => $objective->baseline_value,
                    'baseline_year' => $objective->baseline_year,
                    'performers' => [$departmentId],
                    'july_target' => $objective->july_target,
                    'august_target' => $objective->august_target,
                    'september_target' => $objective->september_target,
                    'q1_target' => $objective->q1_target,
                    'october_target' => $objective->october_target,
                    'november_target' => $objective->november_target,
                    'december_target' => $objective->december_target,
                    'q2_target' => $objective->q2_target,
                    'january_target' => $objective->january_target,
                    'february_target' => $objective->february_target,
                    'march_target' => $objective->march_target,
                    'q3_target' => $objective->q3_target,
                    'april_target' => $objective->april_target,
                    'may_target' => $objective->may_target,
                    'june_target' => $objective->june_target,
                    'q4_target' => $objective->q4_target,
                ]
            );

            PlanItemAssignment::firstOrCreate(
                ['plan_item_id' => $planItem->id, 'performer_id' => $departmentId],
                [
                    'status' => 'pending_chief_approval',
                    'sent_by' => $approver->id,
                    'sent_at' => now(),
                ]
            );
        }
    }

    private function legacyPlanUnit(?string $unit): string
    {
        $normalized = strtolower(trim((string) $unit));
        if (str_contains($normalized, '%') || str_contains($normalized, 'percent')) return 'percent';
        if (str_contains($normalized, 'ton')) return 'tons';
        if (str_contains($normalized, 'birr') || str_contains($normalized, 'currency')) return 'currency';
        if (str_contains($normalized, 'day')) return 'days';
        if (preg_match('/number|count|\bno\.?\b|\bnr\.?\b|set|teu|wagon|passenger|kilomet|km|hour|hrs?/', $normalized)) return 'number';
        return 'na';
    }
}
