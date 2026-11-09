<?php
namespace App\Services;

use App\Models\PlanItem;
use App\Models\StrategicObjective;
use Illuminate\Support\Collection;

class ApprovedPlanExport
{
    public const PERIODS = [
        'july'=>'Jul','august'=>'Aug','september'=>'Sep','q1'=>'Q1',
        'october'=>'Oct','november'=>'Nov','december'=>'Dec','q2'=>'Q2',
        'january'=>'Jan','february'=>'Feb','march'=>'Mar','q3'=>'Q3',
        'april'=>'Apr','may'=>'May','june'=>'Jun','q4'=>'Q4',
    ];

    /** One row per objective, using the lead department's approved plan when available. */
    public function items(Collection $plans, ?Collection $selectedItems = null): Collection
    {
        $candidates = $plans->flatMap(function ($plan) use ($selectedItems) {
            return ($selectedItems ?? $plan->items)->map(function ($item) use ($plan) {
                $copy = clone $item;
                $copy->setRelation('plan', $plan);
                return $copy;
            });
        })->filter(fn ($item) => $item->strategicObjective);

        return $candidates->groupBy('strategic_objective_id')->map(function ($copies) {
            $ordered = $copies->sort(function ($a, $b) {
                $lead = $a->strategicObjective->lead_department_id ?: $a->strategicObjective->department_id;
                return [(int)$b->plan->department_id === (int)$lead, $b->plan->id, $b->id]
                    <=> [(int)$a->plan->department_id === (int)$lead, $a->plan->id, $a->id];
            });
            $item = clone $ordered->first();
            $item->performers = $copies->flatMap(fn ($copy) => collect($copy->performers ?? [])->push($copy->plan->department_id))
                ->filter()->map(fn ($id) => (int)$id)->unique()->values()->all();
            return $item;
        })->sortBy(fn ($item) => $item->strategicObjective->code, SORT_NATURAL)->values();
    }

    public function implementers(PlanItem|StrategicObjective $item, Collection $departmentNames, ?int $fallbackDepartmentId = null): array
    {
        $objective=$item instanceof PlanItem ? $item->strategicObjective : $item;
        $lead=$objective?->lead_department_id ?: ($objective?->department_id ?: $fallbackDepartmentId);
        return collect([$lead])->merge($objective?->performers ?? [])->merge($item instanceof PlanItem ? ($item->performers ?? []) : [])
            ->filter()->map(fn($id)=>(int)$id)->unique()->map(function($id) use($departmentNames,$lead) {
                $name=$departmentNames->get($id);
                return $name ? $name.((int)$lead===$id ? ' (Lead)' : '') : null;
            })->filter()->values()->all();
    }
}
