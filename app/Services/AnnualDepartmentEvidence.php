<?php

namespace App\Services;

use App\Models\Department;
use App\Models\HistoricalPerformanceImport;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Support\Collection;

/** Read-only annual evidence. No KPI matching between departments is involved. */
class AnnualDepartmentEvidence
{
    public function __construct(
        private readonly DashboardWorkspace $workspace,
        private readonly HistoricalAnnualResult $annual,
        private readonly EdrPerformanceCalculator $calculator,
        private readonly PeriodWeightNormalizer $weights,
        private readonly ObjectivePerformerMatcher $performers,
    ) {}

    public function load(User $user, array $source, Collection $departments): array
    {
        return $source['kind'] === 'history' ? $this->historical($source, $departments) : $this->native($user, $source, $departments);
    }

    private function native(User $user, array $source, Collection $departments): array
    {
        $items = $this->workspace->items($user, $source['year'], $departments->pluck('id')->all())
            ->with(['plan.department', 'strategicObjective', 'performances', 'weeklyPerformances' => fn ($q) => $q->where('year', $source['year'])])->get();
        $objectives = $items->pluck('strategicObjective')->filter()->keyBy('id');
        for ($depth = 0; $depth < 40; $depth++) {
            $missing = $objectives->pluck('parent_id')->filter()->diff($objectives->keys());
            if ($missing->isEmpty()) {
                break;
            }
            $parents = StrategicObjective::whereIn('id', $missing)->get();
            if ($parents->isEmpty()) {
                break;
            }
            foreach ($parents as $parent) {
                $objectives->put($parent->id, $parent);
            }
        }
        // Resolve annual targets in memory before normalizing annual weights.
        // Approved allocations remain authoritative; no model is saved.
        foreach ($items as $item) {
            $item->annual_target = $item->getPlannedTargetForPeriod('annual');
        }
        $effective = collect();
        foreach ($items->groupBy('plan_id') as $planItems) {
            $planItems->first()->plan->setRelation('items', $planItems);
            foreach ($this->weights->forPeriod($planItems, 'annual') as $id => $weight) {
                $effective->put($id, $weight);
            }
        }
        $byDepartment = collect();
        foreach ($departments as $department) {
            $owned = $items->filter(fn ($i) => (int) $i->plan->department_id === (int) $department->id);
            $containers = collect();
            foreach ($owned->filter(fn ($item) => $item->strategicObjective && ! $item->strategicObjective->is_reference_only && ! $item->strategicObjective->exclude_from_overall_score) as $item) {
                foreach ($this->ancestors($item->strategicObjective?->parent_id, $objectives) as $parentId) {
                    $containers->put($parentId, true);
                }
            }
            $records = $owned->map(function ($item) use ($effective, $containers) {
                $objective = $item->strategicObjective;
                $base = $this->record('o:'.$item->strategic_objective_id, $objective?->code ?? '', $objective?->description ?? 'Unavailable objective');
                $base = array_replace($base, ['kpi' => $objective?->kpi_code, 'unit' => $objective?->unit ?: $item->unit, 'weight' => $effective->get($item->id),
                    'source' => 'IPRPMS approved plan #'.$item->plan_id, 'sourceRow' => null, 'planId' => $item->plan_id,
                    'direction' => $objective?->goal_direction ?? 'increase', 'target' => $item->annual_target]);
                if (! $objective || $objective->is_reference_only || $objective->exclude_from_overall_score) {
                    return array_replace($base, ['eligible' => false, 'status' => 'excluded', 'reason' => 'Reference-only or excluded from overall scoring.']);
                }
                if ($containers->has($objective->id)) {
                    return array_replace($base, ['eligible' => false, 'status' => 'container', 'reason' => 'Container: component activities are counted once instead.']);
                }
                $official = clone $item;
                $official->setRelation('performances', $item->performances->filter(fn ($p) => $p->actual_value !== null && $this->workspace->isOfficial($p))->values());
                $official->setRelation('weeklyPerformances', $item->weeklyPerformances->filter(fn ($p) => $p->actual_value !== null && $this->workspace->isOfficial($p))->values());
                $result = $this->annual->forItem($official);
                $raw = $result['actual'] === null || $result['target'] === null ? null : $this->calculator->rawAchievement((float) $result['actual'], (float) $result['target'], $base['direction']);
                $reviewed = $result['complete'] && $raw !== null;

                return array_replace($base, ['actual' => $result['actual'], 'target' => $result['target'], 'rawAchievement' => $raw, 'achievement' => $raw === null ? null : min(100, max(0, $raw)),
                    'reviewed' => $reviewed, 'completed' => $result['completed'], 'expected' => $result['expected'],
                    'status' => $reviewed ? 'ready' : ($result['actual'] === null ? 'awaiting' : 'partial'),
                    'reason' => $reviewed ? 'Final reviewed annual evidence.' : ($result['actual'] === null ? 'Awaiting final reviewed annual results.' : 'Incomplete annual reporting schedule; progress is not a final annual result.')]);
            });
            // Two approved records for the same departmental objective are not two activities.
            $records = $records->groupBy('id')->map(function ($group) {
                $first = $group->first();
                if ($group->count() === 1 || ! $first['eligible']) {
                    return $first;
                }

                return array_replace($first, ['reviewed' => false, 'weight' => null, 'achievement' => null, 'rawAchievement' => null, 'actual' => null, 'status' => 'duplicate',
                    'reason' => 'Multiple approved plan records for this department/objective require review. No record was arbitrarily selected.', 'duplicatePlans' => $group->pluck('planId')->all()]);
            })->sortBy('code', SORT_NATURAL)->values();
            $byDepartment->put($department->id, $records);
        }

        return ['byDepartment' => $byDepartment, 'filename' => 'IPRPMS final reviewed annual performance', 'attributionWarnings' => collect()];
    }

    private function historical(array $source, Collection $departments): array
    {
        $import = HistoricalPerformanceImport::with('kpis')->where('year', $source['year'])->latest('id')->first();
        $rows = $import?->kpis ?? collect();
        $byCode = $rows->keyBy('code');
        $parents = [];
        foreach ($rows as $row) {
            $parts = explode('.', (string) $row->code);
            array_pop($parts);
            $parent = null;
            while ($parts) {
                $candidate = implode('.', $parts);
                if ($byCode->has($candidate)) {
                    $parent = $candidate;
                    break;
                } array_pop($parts);
            }
            $parents[$row->id] = $parent ?? ($row->parent_code !== $row->code && $byCode->has($row->parent_code) ? $row->parent_code : null);
        }
        $containers = collect(array_values($parents))->filter()->unique();
        // Include inactive names when resolving responsibility: a shared active/inactive
        // assignment must not turn into an apparently exclusive active assignment.
        $allDepartments = Department::all(['id', 'name', 'code', 'is_active']);
        $byDepartment = $departments->mapWithKeys(fn ($d) => [$d->id => collect()]);
        $warnings = collect();
        foreach ($rows as $row) {
            $text = trim((string) $row->performers);
            $ownerRow = $row;
            $seen = [];
            while ($text === '' && ($parent = $parents[$ownerRow->id] ?? null) && ! in_array($parent, $seen, true)) {
                $seen[] = $parent;
                $ownerRow = $byCode->get($parent);
                $text = trim((string) $ownerRow->performers);
            }
            $ownership = $this->performers->match($text, $allDepartments);
            $shared = count($ownership['ids']) > 1 || count($ownership['unmatched']) > 0 || preg_match('/\ball[\s_-]*departments\b/iu', $text) === 1;
            $isContainer = $containers->contains($row->code) || ($row->is_main_objective && ! $row->kpi_code);
            $record = $this->record('h:'.$row->id, (string) $row->code, $row->description);
            $raw = $row->reported_achievement === null ? null : (float) $row->reported_achievement;
            if ($raw === null && $row->direction_status === 'confirmed' && $row->annual_actual !== null && $row->annual_target !== null) {
                $raw = $this->calculator->rawAchievement((float) $row->annual_actual, (float) $row->annual_target, $row->goal_direction ?: 'increase');
            }
            $reason = $raw === null ? ($row->annual_actual !== null && $row->annual_target !== null ? 'Confirm achievement direction before calculating the missing historical percentage.' : 'Annual achievement or usable annual values are missing.') : 'Original reported annual evidence.';
            $reviewed = $raw !== null && ! $shared && in_array($import->status, ['locked', 'approved'], true);
            $status = $reviewed ? 'ready' : 'awaiting';
            if ($shared) {
                $status = 'shared';
                $reason = 'Shared or partly unresolved historical responsibility: the workbook does not supply independent departmental results or an approved allocation.';
            } elseif (! in_array($import->status, ['locked', 'approved'], true)) {
                $status = 'source_review';
                $reason = 'The historical source is not locked or approved.';
            }
            $record = array_replace($record, ['eligible' => ! $isContainer, 'kpi' => $row->kpi_code, 'unit' => $row->unit, 'weight' => $row->weight === null ? null : (float) $row->weight,
                'target' => $row->annual_target === null ? null : (float) $row->annual_target, 'actual' => $row->annual_actual === null ? null : (float) $row->annual_actual,
                'achievement' => $raw === null ? null : min(100, max(0, $raw)), 'rawAchievement' => $raw, 'reviewed' => $reviewed, 'completed' => $reviewed ? 1 : 0, 'expected' => 1,
                'source' => $import->original_filename, 'sourceRow' => $row->source_row, 'performers' => $text, 'inheritedFrom' => $ownerRow->id !== $row->id ? $ownerRow->code : null,
                'status' => $isContainer ? 'container' : $status, 'reason' => $isContainer ? 'Container: component activities are counted once instead.' : $reason]);
            if (! $isContainer && (! $ownership['ids'] || $ownership['unmatched'] || $shared)) {
                $warnings->push(['code' => $row->code, 'description' => $row->description, 'performers' => $text, 'sourceRow' => $row->source_row, 'unmatched' => $ownership['unmatched'], 'reason' => $reason]);
            }
            foreach ($ownership['ids'] as $id) {
                if ($byDepartment->has($id)) {
                    $byDepartment[$id]->push($record);
                }
            }
        }

        return ['byDepartment' => $byDepartment, 'filename' => $import?->original_filename ?? 'No imported annual source', 'attributionWarnings' => $warnings];
    }

    private function record(string $id, string $code, string $description): array
    {
        return ['id' => $id, 'code' => $code, 'description' => $description, 'eligible' => true, 'kpi' => null, 'unit' => null, 'weight' => null, 'target' => null, 'actual' => null,
            'achievement' => null, 'rawAchievement' => null, 'reviewed' => false, 'completed' => 0, 'expected' => 0, 'status' => 'awaiting', 'reason' => 'Awaiting annual evidence.',
            'source' => null, 'sourceRow' => null, 'performers' => null, 'inheritedFrom' => null];
    }

    private function ancestors(?int $id, Collection $objectives): array
    {
        $seen = [];
        for ($depth = 0; $id && $depth < 40; $depth++) {
            if (in_array($id, $seen, true)) {
                break;
            } $seen[] = $id;
            $id = $objectives->get($id)?->parent_id;
        }

        return $seen;
    }
}
