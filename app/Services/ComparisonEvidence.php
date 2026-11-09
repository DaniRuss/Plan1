<?php

namespace App\Services;

use App\Models\Department;
use App\Models\HistoricalPerformanceImport;
use App\Models\StrategicObjective;
use App\Models\User;

/** Read-only, authorized evidence shared by annual and selected-entity comparisons. */
class ComparisonEvidence
{
    public const MONTHS = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

    public function __construct(private readonly DashboardWorkspace $workspace, private readonly EdrPerformanceCalculator $calculator) {}

    public function load(User $user, array $source, ?int $department, string $frequency, string $period, string $scope): array
    {
        return $source['kind'] === 'history' ? $this->historical($user, $source, $department, $frequency) : $this->native($user, $source, $department, $frequency, $period, $scope);
    }

    private function historical(User $user, array $source, ?int $department, string $frequency): array
    {
        $import = HistoricalPerformanceImport::with('kpis.mappings')->where('year', $source['year'])->latest('id')->first();
        $kpis = $import?->kpis ?? collect();
        $attributed = null;
        if (! $this->workspace->organizationWide($user) || $department) {
            $department ??= (int) ($user->department_id ?? $user->employee?->department_id) ?: null;
            $owned = app(AnnualDepartmentEvidence::class)->load($user, $source, Department::whereKey($department)->get());
            $attributed = $owned['byDepartment']->get($department, collect())->keyBy('id');
            $kpis = $kpis->filter(fn ($k) => $attributed->has('h:'.$k->id));
        }
        if ($frequency !== 'all') {
            $ids = $this->workspace->items($user, null, $department ? [$department] : null)->when($frequency !== 'all', fn ($q) => $q->forReportingFrequency($frequency))->pluck('strategic_objective_id');
            $visible = $kpis->filter(fn ($k) => $k->mappings->contains(fn ($m) => $ids->contains($m->current_objective_id)));
            $parents = $visible->pluck('parent_code')->filter();
            $kpis = $kpis->filter(fn ($k) => $visible->contains('id', $k->id) || $parents->contains($k->code));
        }
        $byCode = $kpis->keyBy('code');
        $parents = $kpis->mapWithKeys(function ($k) use ($byCode) {
            $parts = explode('.', (string) $k->code);
            array_pop($parts);
            while ($parts) {
                $code = implode('.', $parts);
                if ($byCode->has($code)) {
                    return [$k->id => $byCode->get($code)];
                }
                array_pop($parts);
            }

            return [$k->id => filled($k->parent_code) && $k->parent_code !== $k->code ? $byCode->get($k->parent_code) : null];
        });
        $entities = $kpis->map(function ($k) use ($parents, $import, $attributed) {
            $ancestors = [];
            $parent = $parents->get($k->id);
            for ($depth = 0; $parent && $depth < 40; $depth++) {
                if (! $parent || in_array('h:'.$parent->id, $ancestors, true)) {
                    break;
                }
                $ancestors[] = 'h:'.$parent->id;
                $parent = $parents->get($parent->id);
            }
            $raw = $k->reported_achievement === null ? ($k->direction_status !== 'confirmed' || $k->annual_actual === null || $k->annual_target === null ? null : $this->calculator->rawAchievement((float) $k->annual_actual, (float) $k->annual_target, $k->goal_direction ?: 'increase')) : (float) $k->reported_achievement;
            $owned = $attributed?->get('h:'.$k->id);
            $complete = $owned ? $owned['reviewed'] : ($raw !== null && in_array($import->status, ['locked', 'approved'], true));

            return ['id' => 'h:'.$k->id, 'code' => $k->code, 'kpi' => strtoupper(trim($k->kpi_code ?? '')), 'description' => $k->description, 'unit' => $k->unit, 'direction' => $k->goal_direction ?: 'increase', 'directionConfirmed' => $k->direction_status === 'confirmed',
                'ancestors' => $ancestors, 'isParent' => (bool) $k->is_main_objective || $parents->contains(fn ($p) => $p?->id === $k->id), 'scorable' => true, 'department' => $k->performers ?: '',
                'weight' => $k->weight === null ? null : (float) $k->weight,
                'compositeCandidate' => $k->annual_target === null && $k->annual_actual === null && $k->reported_achievement === null && $parents->contains(fn ($p) => $p?->id === $k->id),
                'sourceComplete' => $raw !== null && in_array($import->status, ['locked', 'approved'], true),
                'target' => $k->annual_target === null ? null : (float) $k->annual_target, 'actual' => $k->annual_actual === null ? null : (float) $k->annual_actual, 'achievement' => $raw === null ? null : min(100, max(0, round($raw, 2))), 'rawAchievement' => $raw,
                'complete' => $complete, 'completed' => $complete ? 1 : 0, 'expected' => 1, 'source' => $import->original_filename, 'sourceRow' => $k->source_row, 'reason' => $owned['reason'] ?? ($complete ? '' : 'Awaiting final reviewed historical evidence.'),
                'links' => $k->mappings->map(fn ($m) => ['id' => 'o:'.$m->current_objective_id, 'status' => $m->status, 'type' => $m->mapping_type, 'comparable' => $m->comparable])->all()];
        })->values();

        return ['source' => $source, 'filename' => $import?->original_filename] + app(ComparisonCompositeRollup::class)->apply($entities, $entities->filter(fn ($e) => ! $e['isParent'] || $e['kpi'] !== '')->values());
    }

    private function native(User $user, array $source, ?int $department, string $frequency, string $period, string $scope): array
    {
        $items = $this->workspace->items($user, $source['year'], $department ? [$department] : null)->when($frequency !== 'all', fn ($q) => $q->forReportingFrequency($frequency))
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
        $parentIds = $objectives->pluck('parent_id')->filter();
        $weights = collect();
        foreach ($items->groupBy('plan_id') as $planItems) {
            $planItems->first()->plan->setRelation('items', $planItems);
            foreach (app(PeriodWeightNormalizer::class)->forPeriod($planItems, $period) as $itemId => $weight) {
                $weights->put($itemId, $weight);
            }
        }
        $entities = $objectives->map(function ($o) use ($objectives, $parentIds, $source) {
            $ancestors = [];
            $id = $o->parent_id;
            for ($depth = 0; $id && $depth < 40; $depth++) {
                if (in_array('o:'.$id, $ancestors, true)) {
                    break;
                }
                $ancestors[] = 'o:'.$id;
                $id = $objectives->get($id)?->parent_id;
            }

            return ['id' => 'o:'.$o->id, 'code' => $o->code, 'kpi' => strtoupper(trim($o->kpi_code ?? '')), 'description' => $o->description, 'unit' => $o->unit, 'direction' => $o->goal_direction ?: 'increase', 'directionConfirmed' => true,
                'ancestors' => $ancestors, 'isParent' => $parentIds->contains($o->id), 'scorable' => ! $o->is_reference_only && ! $o->exclude_from_overall_score,
                'includeInRollup' => $o->include_in_parent_rollup !== false, 'weight' => $o->weight === null ? null : (float) $o->weight,
                'manualOverride' => $o->summary_type === 'manual_override',
                'compositeCandidate' => $parentIds->contains($o->id) && $o->summary_type !== 'manual_override' && $o->annual_target === null,
                'department' => '', 'target' => null, 'actual' => null, 'achievement' => null, 'rawAchievement' => null, 'complete' => false, 'completed' => 0, 'expected' => 0, 'source' => 'IPRPMS approved plan '.$source['year'], 'sourceRow' => null, 'reason' => 'No accountable performance record is available.', 'links' => []];
        })->keyBy('id');
        $records = $items->groupBy('strategic_objective_id')->map(function ($group, $id) use ($entities, $department, $period, $scope, $weights) {
            $record = $entities->get('o:'.$id);
            $objective = $group->first()->strategicObjective;
            $lead = $objective->lead_department_id ?: $objective->department_id;
            if (! $department && $lead) {
                $group = $group->filter(fn ($i) => (int) $i->plan->department_id === (int) $lead);
            }
            if ($group->count() !== 1) {
                return array_replace($record, ['reason' => $group->isEmpty() ? 'Awaiting the accountable department result.' : 'Multiple accountable records need a department selection.']);
            }
            $item = clone $group->first();
            $official = $period === 'annual' || $scope === 'official';
            $item->setRelation('performances', $item->performances->filter(fn ($p) => $p->actual_value !== null && (! $official || $this->workspace->isOfficial($p)))->values());
            $item->setRelation('weeklyPerformances', $item->weeklyPerformances->filter(fn ($p) => $p->actual_value !== null && (! $official || $this->workspace->isOfficial($p)))->values());
            $measurement = $this->measurement($item, $period);
            $actual = $measurement['actual'];
            $target = $measurement['target'];

            return array_replace($record, ['department' => $item->plan->department?->name ?? '', 'unit' => $item->unit ?: $objective->unit,
                'weight' => $record['isParent'] ? app(PeriodWeightNormalizer::class)->baseWeight($item) : $weights->get($item->id),
                'compositeCandidate' => $record['compositeCandidate'] && $actual === null && ($target === null || (float) $target === 0.0),
                'target' => $target === null ? null : (float) $target, 'actual' => $actual, 'complete' => $measurement['complete'], 'completed' => $measurement['completed'], 'expected' => $measurement['expected'],
                'achievement' => $actual === null || $target === null ? null : $this->calculator->achievement($actual, (float) $target, $record['direction']),
                'rawAchievement' => $actual === null || $target === null ? null : $this->calculator->rawAchievement($actual, (float) $target, $record['direction']),
                'reason' => $actual === null ? 'Awaiting current results.' : (! $measurement['complete'] ? 'Incomplete reporting period; progress is not a final result.' : '')]);
        })->values();

        $frame = app(ComparisonCompositeRollup::class)->apply($entities->values(), $records);
        $frame['records'] = $frame['records']->filter(fn ($r) => ! $r['isParent'] || $r['kpi'] !== '')->values();

        return ['source' => $source, 'filename' => 'IPRPMS approved plans and recorded performance'] + $frame;
    }

    private function measurement($item, string $period): array
    {
        if ($period === 'annual') {
            return app(HistoricalAnnualResult::class)->forItem($item);
        }
        $target = $item->getPlannedTargetForPeriod($period);
        $explicit = $item->performances->first(fn ($p) => in_array(strtolower($p->period), [$period, $item->plan->year.'-'.$period], true));
        if ($explicit) {
            return ['target' => $target, 'actual' => (float) $explicit->actual_value, 'complete' => true, 'completed' => 1, 'expected' => 1];
        }
        $quarters = ['q1' => array_slice(self::MONTHS, 0, 3), 'q2' => array_slice(self::MONTHS, 3, 3), 'q3' => array_slice(self::MONTHS, 6, 3), 'q4' => array_slice(self::MONTHS, 9, 3)];
        $months = $quarters[$period] ?? [$period];
        $item->annual_target = $target;
        foreach (self::MONTHS as $month) {
            if (! in_array($month, $months, true)) {
                $item->{$month.'_target'} = null;
            }
        }
        $item->setRelation('performances', $item->performances->filter(fn ($p) => in_array(preg_replace('/^\d{4}-/', '', strtolower($p->period)), $months, true))->values());
        $item->setRelation('weeklyPerformances', $item->weeklyPerformances->filter(fn ($p) => in_array(strtolower($p->month), $months, true))->values());

        return app(HistoricalAnnualResult::class)->forItem($item);
    }
}
