<?php

namespace App\Services;

use App\Models\{Department, HistoricalKpi, HistoricalKpiMapping, HistoricalPerformanceImport, StrategicObjective, User};
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class HistoricalPerformanceComparison
{
    public function __construct(private readonly DashboardWorkspace $workspace, private readonly EdrPerformanceCalculator $calculator) {}

    public function build(User $user, Request $request): array
    {
        $organizationWide = $this->workspace->organizationWide($user);
        $departments = Department::where('is_active', true)
            ->when(!$organizationWide, fn ($q) => $q->whereIn('id', array_filter([$user->department_id ?? $user->employee?->department_id])))
            ->orderBy('name')->get(['id','name']);
        $finalOnly = $user->isHeadOffice() && !$user->isSystemAdmin() && !$user->isPlanReportHeadOffice();
        $input = $request->validate([
            'performance_year'=>'nullable|integer|min:1900|max:9999', 'historical_year'=>'nullable|integer|min:1900|max:9999',
            'performance_department'=>['nullable','integer',Rule::in($departments->pluck('id')->all())],
            'movement'=>'nullable|in:all,improved,declined,unchanged,unmatched',
            'mapping_type'=>['nullable',Rule::in(array_merge(['all','unmapped'], HistoricalKpiMapping::TYPES))],
            'main_objective'=>'nullable|string|max:50', 'search'=>'nullable|string|max:200',
            'comparison_sort'=>'nullable|in:code,change,achievement,name', 'edit_kpi'=>'nullable|integer',
            'reporting_frequency'=>'nullable|in:all,daily,weekly,monthly',
            'result_scope'=>['nullable',Rule::in($finalOnly ? ['official'] : ['official','reported'])],
        ]);
        $years = $this->workspace->items($user)->join('plans','plans.id','=','plan_items.plan_id')->distinct()->orderByDesc('plans.year')->pluck('plans.year')->map(fn ($v) => (int)$v);
        $year = (int)($input['performance_year'] ?? $years->first() ?? 2026);
        $historicalYears = HistoricalPerformanceImport::distinct()->orderByDesc('year')->pluck('year')->map(fn ($v) => (int)$v);
        $historicalYear = (int)($input['historical_year'] ?? $historicalYears->first() ?? 2018);
        // Preserve EFY source records; accept the equivalent Gregorian fiscal-start identifier.
        if (!$historicalYears->contains($historicalYear) && $historicalYears->contains($historicalYear - 7)) $historicalYear -= 7;
        if ($year === $historicalYear + 1 && $years->contains($year + 7)) $year += 7;
        $department = isset($input['performance_department']) ? (int)$input['performance_department'] : null;
        $movement = $input['movement'] ?? 'all';
        $mappingType = $input['mapping_type'] ?? 'all';
        $mainObjective = trim($input['main_objective'] ?? '');
        $search = trim($input['search'] ?? '');
        $sort = $input['comparison_sort'] ?? 'code';
        $frequency = $input['reporting_frequency'] ?? 'all';
        // This shared preference is carried to the focused reporting views.
        // Annual comparisons themselves always use final reviewed results.
        $reportingScope = $input['result_scope'] ?? 'official';
        $import = HistoricalPerformanceImport::with('kpis.mappings.currentObjective')->where('year',$historicalYear)->latest('id')->first();
        $historical = $import?->kpis->filter(fn ($k) => !$k->is_main_objective || filled($k->kpi_code))->values() ?? collect();
        $currentItems = $this->workspace->items($user, $year, $department ? [$department] : $departments->pluck('id')->all())
            ->when($frequency !== 'all',fn($q)=>$q->forReportingFrequency($frequency))
            ->with(['plan.department','strategicObjective','performances','weeklyPerformances'=>fn ($q) => $q->where('year',$year)])->get();
        foreach ($currentItems as $item) {
            $item->setRelation('performances', $item->performances->filter(fn ($p) => $this->workspace->isOfficial($p)));
            $item->setRelation('weeklyPerformances', $item->weeklyPerformances->filter(fn ($p) => $this->workspace->isOfficial($p)));
        }
        $currentByObjective = $currentItems->groupBy('strategic_objective_id');
        $scoped = !$organizationWide || $department !== null || $frequency !== 'all';
        if ($scoped) $historical = $historical->filter(fn ($k) => $k->mappings->contains(fn ($m) => $currentByObjective->has($m->current_objective_id)))->values();
        $rows = $historical->flatMap(function (HistoricalKpi $kpi) use ($currentByObjective, $year, $scoped, $department) {
            $mappings = $kpi->mappings->filter(fn ($m) => $m->currentObjective
                ? (int)$m->currentObjective->target_year === $year && (!$scoped || $currentByObjective->has($m->current_objective_id))
                : !$scoped && in_array($m->mapping_type,['retired','not_comparable'],true));
            if ($mappings->isEmpty()) return [$this->row($kpi)];
            return $mappings->map(function ($mapping) use ($kpi, $currentByObjective, $department) {
                $row = $this->row($kpi, $mapping);
                if (!$mapping->currentObjective) return array_replace($row, ['readiness'=>'definition_review','reason'=>'This historical KPI is intentionally '.str_replace('_',' ',$mapping->mapping_type).'; no current equivalent is expected.']);
                $items = $currentByObjective->get($mapping->current_objective_id, collect());
                $lead = $mapping->currentObjective->lead_department_id ?: $mapping->currentObjective->department_id;
                if (!$department && $lead) $items = $items->filter(fn ($i) => (int)$i->plan->department_id === (int)$lead);
                if ($items->count() > 1) return array_replace($row, ['readiness'=>'department_review', 'reason'=>'Multiple plan records need a single accountable result. Select a department or resolve duplicate plan items.']);
                $item = $items->first();
                $annual = $item ? app(HistoricalAnnualResult::class)->forItem($item) : ['target'=>null,'actual'=>null,'complete'=>false,'completed'=>0,'expected'=>0];
                $row = array_replace($row, [
                    'currentTarget'=>$annual['target'] === null ? null : (float)$annual['target'], 'currentActual'=>$annual['actual'],
                    'currentComplete'=>$annual['complete'], 'completedPeriods'=>$annual['completed'], 'expectedPeriods'=>$annual['expected'],
                    'department'=>$item?->plan->department?->name ?? '',
                ]);
                if ($row['currentActual'] !== null && $row['currentTarget'] !== null) {
                    $row['currentAchievement'] = $this->calculator->achievement($row['currentActual'], $row['currentTarget'], $mapping->currentObjective->goal_direction ?? 'increase');
                    $row['currentRawAchievement'] = $this->calculator->rawAchievement($row['currentActual'], $row['currentTarget'], $mapping->currentObjective->goal_direction ?? 'increase');
                }
                [$readiness, $reason] = $this->readiness($kpi, $mapping, $row);
                $row['readiness'] = $readiness;
                $row['reason'] = $reason;
                if ($readiness === 'ready') {
                    $row['delta'] = round($row['currentAchievement'] - $row['historicalAchievement'], 2);
                    $row['actualChange'] = $row['historicalActual'] === null ? null : round($row['currentActual'] - $row['historicalActual'], 4);
                    $row['targetChange'] = $row['historicalTarget'] === null ? null : round($row['currentTarget'] - $row['historicalTarget'], 4);
                    $row['movement'] = $row['delta'] > 0 ? 'improved' : ($row['delta'] < 0 ? 'declined' : 'unchanged');
                }
                return $row;
            });
        })->values();
        if ($historical->contains(fn ($k) => $k->is_main_objective && filled($k->kpi_code) && $k->annual_target === null && $k->annual_actual === null)) {
            $provider = app(ComparisonEvidence::class);
            $referenceEntities = $provider->load($user, ['kind'=>'history','year'=>$historicalYear], $department, $frequency, 'annual', 'official')['entities']->keyBy('id');
            $currentEntities = $provider->load($user, ['kind'=>'plan','year'=>$year], $department, $frequency, 'annual', 'official')['entities']->keyBy('id');
            $rows = $rows->map(function ($row) use ($referenceEntities, $currentEntities) {
                $reference = $referenceEntities->get('h:'.$row['historicalId']);
                if (($reference['measureType'] ?? null) !== 'composite') return $row;
                $current = $currentEntities->get('o:'.$row['currentObjectiveId']);
                $pair = app(ComparisonWorkbench::class)->pair($reference, $current, 'annual', 'annual', 'kpi');
                $readiness = $pair['delta'] !== null ? 'ready' : ($current === null || $current['achievement'] === null ? 'missing_current' : 'definition_review');

                return array_replace($row, ['compositePair'=>$pair, 'summaryEligible'=>false,
                    'historicalTarget'=>null,'historicalActual'=>null,'historicalAchievement'=>$reference['achievement'],'historicalRawAchievement'=>$reference['rawAchievement'],
                    'currentTarget'=>$current['target']??null,'currentActual'=>$current['actual']??null,'currentAchievement'=>$current['achievement']??null,'currentRawAchievement'=>$current['rawAchievement']??null,
                    'currentComplete'=>$current['complete']??false,'completedPeriods'=>$current['completed']??0,'expectedPeriods'=>$current['expected']??0,
                    'delta'=>$pair['delta'],'actualChange'=>null,'targetChange'=>null,'movement'=>$pair['delta']===null?'unmatched':$pair['status'],'readiness'=>$readiness,'reason'=>$pair['reason']]);
            });
        }
        $allRows = $rows;
        $mainObjectives = $historical->pluck('parent_code')->filter()->unique()->sort(SORT_NATURAL)->values();
        if ($mainObjective !== '') $rows = $rows->where('mainObjective', $mainObjective);
        if ($mappingType === 'unmapped') $rows = $rows->whereNull('mappingId');
        elseif ($mappingType !== 'all') $rows = $rows->where('mappingType', $mappingType);
        if ($movement !== 'all') $rows = $rows->where('movement', $movement);
        if ($search !== '') $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', array_intersect_key($r, array_flip(['kpiCode','historicalCode','historicalDescription','currentCode','currentDescription','department'])))), mb_strtolower($search)));
        $rows = match ($sort) {
            'change'=>$rows->sortByDesc(fn ($r) => $r['delta'] === null ? -1 : abs($r['delta'])),
            'achievement'=>$rows->sortByDesc(fn ($r) => $r['currentAchievement'] ?? -1),
            'name'=>$rows->sortBy('historicalDescription'),
            default=>$rows->sort(fn ($a,$b) => strnatcasecmp($a['historicalCode'] ?? '', $b['historicalCode'] ?? '')),
        };
        $rows = $rows->values();
        $summaryRows = $rows->filter(fn ($r) => $r['summaryEligible'] ?? true);
        $official = $summaryRows->whereNotNull('delta')->unique('historicalId')->values();
        $baselineRows = $summaryRows->unique('historicalId');
        $historicalAverage = $this->average($official, 'historicalAchievement');
        $currentAverage = $this->average($official, 'currentAchievement');
        $baselineAverage = $this->average($baselineRows, 'historicalAchievement');
        $approvedMatched = $allRows->filter(fn ($r) => $r['mappingStatus'] === 'approved' && $r['comparable'])->unique('historicalId')->count();
        $allLeaf = $historical->count();
        $canManage = $user->isSystemAdmin() || $user->isPlanReportDepartmentExpert() || $user->isPlanReportDirectorate() || $user->isPlanReportHeadOffice();
        $canReview = $user->isSystemAdmin() || $user->isPlanReportDirectorate() || $user->isPlanReportHeadOffice();
        $editKpi = $canManage && isset($input['edit_kpi']) ? $historical->firstWhere('id',(int)$input['edit_kpi']) : null;
        $editMappings = $editKpi ? $editKpi->mappings->filter(fn ($m) => $m->currentObjective ? (int)$m->currentObjective->target_year === $year : in_array($m->mapping_type,['retired','not_comparable'],true)) : collect();
        $objectiveOptions = $editKpi ? StrategicObjective::where('target_year',$year)->where(fn ($q) => $q->where('is_active',true)->orWhereIn('id',$editMappings->pluck('current_objective_id')->filter()))->orderByRaw('LENGTH(code)')->orderBy('code')->get(['id','code','kpi_code','description','unit']) : collect();
        $currentEfyYear = $year === $historicalYear + 1 ? $year : $year - 7;
        $currentGregorianYear = $year === $historicalYear + 1 ? $year + 7 : $year;
        $baselineGregorianYear = $historicalYear + 7;
        return compact('organizationWide','departments','years','year','historicalYear','historicalYears','department','movement','mappingType','mainObjective','mainObjectives','import','rows','historicalAverage','currentAverage','baselineAverage','canManage','canReview','objectiveOptions','editKpi','editMappings','search','sort','currentEfyYear','currentGregorianYear','baselineGregorianYear','frequency','reportingScope') + [
            'method'=>'historical', 'period'=>'annual', 'referenceYear'=>$historicalYear, 'referencePeriod'=>'annual', 'scope'=>'official', 'kpi'=>null, 'periods'=>['annual'=>'Annual results'], 'kpis'=>collect(), 'referenceDepartment'=>null, 'finalOnly'=>$finalOnly,
            'currentLabel'=>"{$currentEfyYear} EFY / {$currentGregorianYear}", 'referenceLabel'=>"{$historicalYear} EFY / {$baselineGregorianYear}",
            'delta'=>$historicalAverage === null || $currentAverage === null ? null : round($currentAverage - $historicalAverage, 2),
            'matched'=>$approvedMatched, 'unmatched'=>max(0,$allLeaf-$approvedMatched), 'coverage'=>$allLeaf ? round($approvedMatched/$allLeaf*100,1) : 0,
            'readyPairs'=>$official->count(), 'readinessCounts'=>$rows->countBy('readiness'), 'movementCounts'=>$official->countBy('movement'),
            'totalHistorical'=>$allLeaf, 'baselineResults'=>$baselineRows->whereNotNull('historicalActual')->count(),
            'newCurrent'=>$currentItems->pluck('strategic_objective_id')->unique()->diff($allRows->pluck('currentObjectiveId')->filter())->count(),
            'mappingCounts'=>$allRows->whereNotNull('mappingId')->unique('mappingId')->countBy('mappingStatus'), 'missingUnits'=>$historical->where('unit_status','missing')->count(),
        ];
    }

    private function readiness(HistoricalKpi $kpi, HistoricalKpiMapping $mapping, array $row): array
    {
        if ($mapping->status !== 'approved') return ['mapping_review','The link between the two KPIs is awaiting mapping review.'];
        if (!$mapping->comparable || !in_array($mapping->mapping_type,['direct','renamed'],true)) return ['definition_review','Changed, split, merged or retired definitions are context only; no automatic growth is calculated.'];
        $normalizer = app(HistoricalPerformanceWorkbook::class);
        $unit = $normalizer->normalizeUnit($kpi->unit);
        if (!$unit || !$normalizer->normalizeUnit($row['currentUnit'])) return ['missing_unit','A measurement unit is unspecified. The source remains visible; growth is not calculated.'];
        if ($unit !== $normalizer->normalizeUnit($row['currentUnit'])) return ['unit_mismatch','The two KPIs use different units. Confirm the measurement before comparing.'];
        if ($kpi->direction_status !== 'confirmed' || $kpi->goal_direction !== ($mapping->currentObjective->goal_direction ?? 'increase')) return ['direction_review','Confirm whether higher or lower values indicate better performance in both years.'];
        if ($row['historicalAchievement'] === null) return ['missing_baseline','The workbook does not contain enough historical result data for this KPI.'];
        if ($row['currentActual'] === null) return ['missing_current','Awaiting current results: no final approved annual or monthly performance is available.'];
        if (!$row['currentComplete']) return ['incomplete_current','Partial year: '.$row['completedPeriods'].' of '.$row['expectedPeriods'].' planned reporting months complete. Not compared with a full annual baseline.'];
        if ($row['currentTarget'] === null) return ['missing_target','The current annual target has not been set.'];
        return ['ready','Approved equivalent KPI with complete annual results on both sides.'];
    }

    private function average(Collection $rows, string $key): ?float
    {
        $valid = $rows->whereNotNull($key);
        return $valid->isEmpty() ? null : round((float)$valid->avg($key),2);
    }

    private function row(HistoricalKpi $kpi, ?HistoricalKpiMapping $mapping = null): array
    {
        $raw = $kpi->reported_achievement === null ? ($kpi->annual_actual === null || $kpi->annual_target === null ? null : $this->calculator->rawAchievement((float)$kpi->annual_actual,(float)$kpi->annual_target,$kpi->goal_direction ?: 'increase')) : (float)$kpi->reported_achievement;
        return [
            'historicalId'=>$kpi->id, 'mappingId'=>$mapping?->id, 'currentObjectiveId'=>$mapping?->current_objective_id,
            'kpiCode'=>$kpi->kpi_code, 'historicalCode'=>$kpi->code, 'originalCode'=>$kpi->original_code, 'sourceRow'=>$kpi->source_row,
            'historicalDescription'=>$kpi->description, 'currentCode'=>$mapping?->currentObjective?->code, 'currentDescription'=>$mapping?->currentObjective?->description,
            'mainObjective'=>$kpi->parent_code, 'unit'=>$kpi->unit, 'currentUnit'=>$mapping?->currentObjective?->unit, 'historicalPerformers'=>$kpi->performers,
            'historicalTarget'=>$kpi->annual_target === null ? null : (float)$kpi->annual_target, 'historicalActual'=>$kpi->annual_actual === null ? null : (float)$kpi->annual_actual,
            'historicalAchievement'=>$raw === null ? null : min(100,max(0,round($raw,2))), 'historicalRawAchievement'=>$raw, 'reportedAchievement'=>$kpi->reported_achievement,
            'currentTarget'=>null, 'currentActual'=>null, 'currentAchievement'=>null, 'currentRawAchievement'=>null, 'currentComplete'=>false, 'completedPeriods'=>0, 'expectedPeriods'=>0,
            'delta'=>null, 'actualChange'=>null, 'targetChange'=>null, 'movement'=>'unmatched', 'readiness'=>'unmapped', 'reason'=>'No equivalent KPI is linked for the selected current plan year.',
            'mappingType'=>$mapping?->mapping_type ?? 'unmapped', 'mappingStatus'=>$mapping?->status ?? 'unmapped', 'comparable'=>$mapping?->comparable ?? false,
            'confidence'=>$mapping?->confidence, 'expertComment'=>$mapping?->expert_comment, 'reviewComment'=>$mapping?->review_comment, 'department'=>'', 'weight'=>$kpi->weight,
        ];
    }
}
