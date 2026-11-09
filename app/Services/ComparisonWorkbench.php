<?php

namespace App\Services;

use App\Models\Department;
use App\Models\HistoricalPerformanceImport;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** One comparison boundary for the complete Master and focused presets. */
class ComparisonWorkbench
{
    public const TYPES = ['year' => 'Year versus year', 'period' => 'Period versus period', 'department' => 'Department over time', 'objective' => 'Objective versus objective', 'kpi' => 'KPI versus KPI', 'target' => 'Actual versus target'];

    public function __construct(private readonly DashboardWorkspace $workspace, private readonly ComparisonEvidence $evidence) {}

    public function build(User $user, Request $request): array
    {
        $wide = $this->workspace->organizationWide($user);
        $departments = Department::where('is_active', true)->when(! $wide, fn ($q) => $q->whereIn('id', array_filter([$user->department_id ?? $user->employee?->department_id])))->orderBy('name')->get(['id', 'name', 'code']);
        $finalOnly = $user->isHeadOffice() && ! $user->isSystemAdmin() && ! $user->isPlanReportHeadOffice();
        $sources = $this->sources($user);
        if ($request->get('comparison_type', $request->get('comparison_method')) === 'department') {
            return app(AnnualDepartmentComparison::class)->build($user, $request, $sources, $departments);
        }
        $periods = ['annual' => 'Full fiscal year'] + array_combine(ComparisonEvidence::MONTHS, array_map('ucfirst', ComparisonEvidence::MONTHS)) + ['q1' => 'Quarter 1', 'q2' => 'Quarter 2', 'q3' => 'Quarter 3', 'q4' => 'Quarter 4', 'all' => 'All recorded months'];
        $input = $request->validate([
            'comparison_type' => ['nullable', Rule::in(array_keys(self::TYPES))], 'comparison_view' => 'nullable|in:master,year,period,department,objective,kpi,target',
            'reference_source' => ['nullable', Rule::in($sources->keys()->all())], 'current_source' => ['nullable', Rule::in($sources->keys()->all())],
            'reference_department' => ['nullable', 'integer', Rule::in($departments->pluck('id')->all())], 'current_department' => ['nullable', 'integer', Rule::in($departments->pluck('id')->all())],
            'performance_department' => ['nullable', 'integer', Rule::in($departments->pluck('id')->all())],
            'performance_period' => ['nullable', Rule::in(array_keys($periods))],
            'reference_entity' => 'nullable|string|max:60', 'current_entity' => 'nullable|string|max:60',
            'reference_period' => ['nullable', Rule::in(array_keys($periods))], 'current_period' => ['nullable', Rule::in(array_keys($periods))],
            'reporting_frequency' => 'nullable|in:all,daily,weekly,monthly', 'result_scope' => ['nullable', Rule::in($finalOnly ? ['official'] : ['official', 'reported'])],
            'movement' => 'nullable|in:all,improved,declined,unchanged,unmatched', 'comparison_sort' => 'nullable|in:code,name,change,achievement',
            'search' => 'nullable|string|max:200', 'configure' => 'nullable|in:1',
        ]);
        $type = $input['comparison_type'] ?? 'year';
        $view = $input['comparison_view'] ?? 'master';
        $frequency = $input['reporting_frequency'] ?? 'all';
        $scope = $input['result_scope'] ?? 'official';
        $native = $sources->where('kind', 'plan')->sortByDesc('year');
        $currentKey = $input['current_source'] ?? $this->sourceForYear($sources, (int) $request->get('performance_year', 0), false) ?? $native->keys()->first() ?? $sources->keys()->first();
        $currentSource = $sources->get($currentKey);
        $prior = $native->first(fn ($s) => $s['year'] < $currentSource['year']);
        $legacyYear = (int) $request->get('historical_year', $request->get('reference_year', 0));
        $preferWorkbook = $request->has('edit_kpi') || ($request->get('comparison_method') === 'historical' && ! $request->has('comparison_type'));
        $referenceKey = $input['reference_source'] ?? $this->sourceForYear($sources, $legacyYear, true) ?? ($preferWorkbook ? null : ($prior['key'] ?? null)) ?? $sources->where('kind', 'history')->keys()->first() ?? $currentKey;
        if (in_array($type, ['period', 'target'], true) && ! isset($input['reference_source'])) {
            $referenceKey = $currentKey;
        }
        $referenceSource = $sources->get($referenceKey);
        $currentDepartment = isset($input['current_department']) ? (int) $input['current_department'] : ($request->filled('performance_department') ? (int) $request->performance_department : null);
        if ($currentDepartment && ! $departments->contains('id', $currentDepartment)) {
            $this->invalid('current_department', 'Choose an authorized department.');
        }
        $referenceDepartment = isset($input['reference_department']) ? (int) $input['reference_department'] : $currentDepartment;
        if (! $wide) {
            $currentDepartment = $referenceDepartment = (int) ($user->department_id ?? $user->employee?->department_id) ?: null;
        }
        $currentPeriod = $input['current_period'] ?? ($request->get('performance_period') ?: ($type === 'period' ? null : 'annual'));
        $referencePeriod = $input['reference_period'] ?? ($type === 'period' ? null : ($referenceSource['kind'] === 'history' ? 'annual' : $currentPeriod));
        // A target is the selected Current indicator's own benchmark, never a stale
        // independent Reference source or period retained by the browser form.
        if ($type === 'target') {
            $referenceKey = $currentKey;
            $referenceSource = $currentSource;
            $referenceDepartment = $currentDepartment;
            $referencePeriod = $currentPeriod;
        }
        $noReference = $type === 'year' && $referenceSource['gregorian'] === $currentSource['gregorian'];
        if ($noReference && isset($input['reference_source'])) {
            $this->invalid('reference_source', 'Choose two different fiscal-year sources.');
        }
        foreach (['reference' => [$referenceSource, $referencePeriod], 'current' => [$currentSource, $currentPeriod]] as $side => [$source,$period]) {
            if ($source['kind'] === 'history' && $period !== null && $period !== 'annual') {
                $this->invalid($side.'_period', 'This imported workbook contains annual results only; no monthly or quarterly data was imported.');
            }
        }
        if ($type === 'period' && $referenceDepartment !== $currentDepartment) {
            $this->invalid('reference_department', 'Period comparison uses the same department on both sides.');
        }
        if ($currentPeriod === 'all' || $referencePeriod === 'all') {
            $this->invalid($referencePeriod === 'all' ? 'reference_period' : ($request->has('performance_period') ? 'performance_period' : 'current_period'), 'Choose a specific month, quarter or full fiscal year for a traceable comparison.');
        }
        // Keep the proven annual historical provider and recorded-period default resolver.
        $legacyHistory = $type === 'year' && $referenceSource['kind'] === 'history' && $currentSource['kind'] === 'plan' && $referenceDepartment === $currentDepartment && $referencePeriod === 'annual' && $currentPeriod === 'annual';
        $needsDefaults = $type === 'period' && (! $currentPeriod || ! $referencePeriod);
        $legacyQuery = array_merge($request->query->all(), ['performance_year' => $currentSource['year'], 'performance_department' => $currentDepartment, 'reporting_frequency' => $frequency, 'result_scope' => $scope]);
        if ($legacyHistory) {
            $legacyQuery['historical_year'] = $referenceSource['year'];
            $legacy = app(HistoricalPerformanceComparison::class)->build($user, $request->duplicate($legacyQuery));
        } elseif ($needsDefaults && ($referenceKey !== $currentKey || $currentSource['kind'] === 'history')) {
            if ($referenceSource['kind'] === 'history' || $currentSource['kind'] === 'history') {
                $currentPeriod ??= 'annual';
                $referencePeriod ??= 'annual';
            } else {
                $currentPeriod ??= $referencePeriod ?? $this->latestPeriod($user, $currentSource, $currentDepartment, $frequency, $scope)
                    ?? $this->latestPeriod($user, $referenceSource, $referenceDepartment, $frequency, $scope);
                $referencePeriod ??= $currentPeriod;
            }
            $legacy = null;
        } elseif ($needsDefaults) {
            $legacyQuery['comparison_method'] = $type;
            unset($legacyQuery['performance_period'],$legacyQuery['reference_period']);
            if ($currentPeriod) {
                $legacyQuery['performance_period'] = $currentPeriod;
            }
            if ($referencePeriod) {
                $legacyQuery['reference_period'] = $referencePeriod;
            }
            $legacyQuery['reference_department'] = $referenceDepartment;
            if (($legacyQuery['comparison_sort'] ?? null) === 'code') {
                unset($legacyQuery['comparison_sort']);
            }
            $defaults = app(PerformanceComparison::class)->standard($user, $request->duplicate($legacyQuery));
            $currentPeriod = $defaults['period'];
            $referencePeriod = $defaults['referencePeriod'];
            $currentDepartment = $defaults['department'];
            $referenceDepartment = $defaults['referenceDepartment'];
            $legacy = null;
        } else {
            $legacy = null;
        }
        $selectionNotice = $defaults['selectionNotice'] ?? null;
        if ($type === 'period') {
            if ($currentPeriod && $referencePeriod) {
                if ($currentPeriod === $referencePeriod && $currentSource['gregorian'] === $referenceSource['gregorian']) {
                    $this->invalid('reference_period', 'Choose different fiscal years or different reporting periods; a result cannot be compared with itself.');
                }
                $length = static fn ($period) => $period === 'annual' ? 'annual' : (str_starts_with($period, 'q') ? 'quarter' : 'month');
                if ($length($currentPeriod) !== $length($referencePeriod)) {
                    $this->invalid('reference_period', 'Compare month with month, quarter with quarter, or annual with annual.');
                }
            } else {
                $selectionNotice ??= 'Choose available reporting periods for the selected fiscal years. Missing performance is not zero.';
            }
        }
        if ($type !== 'period') {
            $currentPeriod ??= 'annual';
        }
        $referencePeriod ??= ($type === 'period' ? null : $currentPeriod);
        // Annual historical results already contain all source and performance
        // evidence. Their year-only form needs no duplicate full catalog load.
        $left = $legacy ? ['entities' => collect(), 'records' => collect()] : $this->evidence->load($user, $referenceSource, $referenceDepartment, $frequency, $referencePeriod ?: 'annual', $scope);
        $right = $legacy ? ['entities' => collect(), 'records' => collect()] : $this->evidence->load($user, $currentSource, $currentDepartment, $frequency, $currentPeriod ?: 'annual', $scope);
        $referenceEntity = $type === 'target' ? null : ($input['reference_entity'] ?? null);
        $currentEntity = $input['current_entity'] ?? null;
        if (! in_array($type, ['objective', 'kpi', 'target'], true)) {
            $referenceEntity = $currentEntity = null;
        }
        if (isset($input['configure'])) {
            $referenceEntity = $currentEntity = null;
        }
        foreach (['reference' => [$left, $referenceEntity], 'current' => [$right, $currentEntity]] as $side => [$frame,$entity]) {
            if ($entity && ! $frame['entities']->contains('id', $entity)) {
                $this->invalid($side.'_entity', 'This selection does not belong to the selected source and authorized department. Refresh the selectors.');
            }
        }
        if (in_array($type, ['kpi', 'objective'], true)) {
            $choices = $type === 'kpi' ? $left['entities']->where('kpi', '!=', '') : $left['entities'];
            $referenceEntity ??= $choices->first()['id'] ?? null;
            $selected = $left['entities']->firstWhere('id', $referenceEntity);
            if (! $currentEntity && $selected) {
                $currentEntity = $this->candidate($selected, $right['entities'])['id'] ?? null;
            }
            if ($type === 'kpi') {
                foreach (['reference' => [$left, $referenceEntity], 'current' => [$right, $currentEntity]] as $side => [$frame,$entity]) {
                    if ($entity && blank($frame['entities']->firstWhere('id', $entity)['kpi'])) {
                        $this->invalid($side.'_entity', 'Select a KPI-coded indicator. Use Objective comparison for activities without a KPI code.');
                    }
                }
            }
            if ($referenceEntity && $referenceEntity === $currentEntity && $referenceKey === $currentKey && $referencePeriod === $currentPeriod && $referenceDepartment === $currentDepartment) {
                if (! empty($input['current_entity']) && ! isset($input['configure'])) {
                    $this->invalid('current_entity', 'Select a different entity, year, department or reporting period; this would compare a result with itself.');
                }
                $currentEntity = null;
            }
        }
        $builder = compact('type', 'view', 'sources', 'departments', 'wide', 'finalOnly', 'frequency', 'scope', 'periods', 'referenceSource', 'currentSource', 'referenceDepartment', 'currentDepartment', 'referencePeriod', 'currentPeriod', 'referenceEntity', 'currentEntity');
        $builder['referenceOptions'] = $this->options($left['entities']);
        $builder['currentOptions'] = $this->options($right['entities']);
        $builder['types'] = array_diff_key(self::TYPES, $wide ? [] : ['department' => true]);
        if ($legacy) {
            return $legacy + ['builder' => $builder, 'activeView' => $view];
        }
        $a = $noReference ? collect() : $this->selected($left, $type, $referenceEntity);
        $b = $this->selected($right, $type, $currentEntity);
        if ($type === 'period' && ! $referencePeriod) {
            $a = collect();
        }
        if ($type === 'period' && ! $currentPeriod) {
            $b = collect();
        }
        if ($type === 'target') {
            $b = $b->map(fn ($r) => array_replace($r, ['achievement' => $r['rawAchievement']]));
            $a = $b->map(fn ($r) => $this->targetBenchmark($r));
        }
        $manual = in_array($type, ['kpi', 'objective'], true) && $a->count() === 1 && $b->count() === 1;
        $paired = collect();
        $used = [];
        foreach ($a as $record) {
            $strictPeriodIdentity = $type === 'period' && $referenceSource['kind'] === 'plan' && $currentSource['kind'] === 'plan' && $referenceSource['gregorian'] !== $currentSource['gregorian'];
            $match = $manual ? $b->first() : $this->candidate($record, $b, $a, $strictPeriodIdentity);
            if ($match && in_array($match['id'], $used, true)) {
                $match = null;
            }
            if ($match) {
                $used[] = $match['id'];
            }
            $paired->push($this->pair($record, $match, $referencePeriod, $currentPeriod, $type));
        }
        foreach ($b->reject(fn ($r) => in_array($r['id'], $used, true)) as $record) {
            $paired->push($this->pair(null, $record, $referencePeriod, $currentPeriod, $type));
        }
        $search = trim($input['search'] ?? '');
        if ($search !== '') {
            $paired = $paired->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', [$r['reference']['description'] ?? '', $r['current']['description'] ?? '', $r['reference']['kpi'] ?? '', $r['current']['kpi'] ?? ''])), mb_strtolower($search)));
        }
        $movement = $input['movement'] ?? 'all';
        if ($movement !== 'all') {
            $paired = $paired->filter(fn ($r) => $movement === 'unmatched' ? $r['delta'] === null : $r['status'] === $movement);
        }
        $sort = $input['comparison_sort'] ?? 'code';
        $paired = match ($sort) {
            'change' => $paired->sortByDesc(fn ($r) => $r['delta'] === null ? -1 : abs($r['delta'])),
            'achievement' => $paired->sortByDesc(fn ($r) => $r['current']['achievement'] ?? -1),
            'name' => $paired->sortBy(fn ($r) => $r['reference']['description'] ?? $r['current']['description']),
            // Imported rows may have neither an activity code nor a counterpart.
            default => $paired->sortBy(fn ($r) => $r['reference']['code'] ?? $r['current']['code'] ?? '', SORT_NATURAL),
        };
        $paired = $paired->values();
        $ready = $paired->whereNotNull('delta');
        $sourceLabel = static fn ($source, $period, $department) => $source['label'].($period && $period !== 'annual' ? ' · '.($periods[$period] ?? $period) : '').($department ? ' · '.$departments->firstWhere('id', $department)?->name : '');

        return ['workspace' => 'paired', 'method' => $type, 'activeView' => $view, 'builder' => $builder, 'pairedRows' => $paired, 'rows' => $paired, 'year' => $currentSource['year'], 'referenceYear' => $referenceSource['year'], 'period' => $currentPeriod, 'referencePeriod' => $referencePeriod, 'organizationWide' => $wide, 'departments' => $departments, 'scope' => $scope, 'frequency' => $frequency, 'sort' => $sort, 'movement' => $movement, 'search' => $search, 'selectionNotice' => $selectionNotice,
            'currentLabel' => $sourceLabel($currentSource, $currentPeriod, $currentDepartment), 'referenceLabel' => $type === 'target' ? 'Planned target (100%)' : $sourceLabel($referenceSource, $referencePeriod, $referenceDepartment),
            'currentSelected' => $right['entities']->firstWhere('id', $currentEntity), 'referenceSelected' => $left['entities']->firstWhere('id', $referenceEntity),
            'currentFilename' => $right['filename'], 'referenceFilename' => $type === 'target' ? 'Planned target benchmark' : $left['filename'],
            'matched' => $ready->count(), 'unmatched' => $paired->whereNull('delta')->count(), 'currentAverage' => $ready->isEmpty() ? null : round((float) $ready->avg('current.achievement'), 2), 'referenceAverage' => $ready->isEmpty() ? null : round((float) $ready->avg('reference.achievement'), 2),
            'delta' => $ready->isEmpty() ? null : round((float) $ready->avg('delta'), 2), 'currentAvailable' => $this->available($paired, 'current'), 'referenceAvailable' => $this->available($paired, 'reference'), 'signals' => $ready->countBy('status')];
    }

    private function latestPeriod(User $user, array $source, ?int $department, string $frequency, string $scope): ?string
    {
        $query = ['comparison_method' => 'period', 'performance_year' => $source['year'], 'performance_department' => $department, 'reporting_frequency' => $frequency, 'result_scope' => $scope];

        return app(PerformanceComparison::class)->standard($user, Request::create('/performance-comparison', 'GET', $query))['period'];
    }

    private function targetBenchmark(array $record): array
    {
        if (($record['measureType'] ?? 'direct') !== 'composite') {
            return array_replace($record, ['actual' => $record['target'], 'achievement' => $record['target'] === null ? null : 100.0,
                'rawAchievement' => 100.0, 'complete' => $record['target'] !== null, 'source' => 'Planned target benchmark', 'reason' => '']);
        }
        $rollup = $record['rollup'];
        $complete = $rollup['weight'] > 0 && $rollup['warnings'] === [];
        $rollup['score'] = $complete ? $rollup['weight'] : null;
        $rollup['completed'] = $complete ? $rollup['expected'] : 0;
        $rollup['components'] = array_map(fn ($component) => array_replace($component, [
            'actual' => $component['target'], 'achievement' => $component['weight'] === null ? null : 100.0,
            'rawAchievement' => 100.0, 'complete' => $component['weight'] !== null, 'componentComplete' => $component['weight'] !== null,
            'contribution' => $component['weight'], 'source' => 'Planned target benchmark',
        ]), $rollup['components']);

        return array_replace($record, ['achievement' => $complete ? 100.0 : null, 'rawAchievement' => $complete ? 100.0 : null,
            'complete' => $complete, 'rollup' => $rollup, 'completed' => $rollup['completed'], 'source' => 'Planned target benchmark', 'reason' => '']);
    }

    private function sources(User $user): Collection
    {
        $years = $this->workspace->items($user)->join('plans', 'plans.id', '=', 'plan_items.plan_id')->distinct()->pluck('plans.year')->map(fn ($y) => (int) $y);
        $objectiveYears = StrategicObjective::where('status', 'approved')->when(! $this->workspace->organizationWide($user), fn ($q) => $q->where('department_id', $user->department_id ?? $user->employee?->department_id))->whereNotNull('target_year')->distinct()->pluck('target_year')->map(fn ($y) => (int) $y);
        // Some legacy objective metadata used EFY identifiers. Prefer the actual
        // authorized plan year, without inventing an empty second native source.
        $objectiveYears = $objectiveYears->map(fn ($year) => ! $years->contains($year) && $years->contains($year + 7) ? $year + 7 : $year);
        $years = $years->merge($objectiveYears)->filter(fn ($y) => $y >= 1900 && $y <= 9999)->unique()->sortDesc();
        if ($years->isEmpty()) {
            $years = collect([now()->year]);
        }
        $sources = collect();
        foreach ($years as $year) {
            $sources->put('plan:'.$year, ['key' => 'plan:'.$year, 'kind' => 'plan', 'year' => $year, 'efy' => $year - 7, 'gregorian' => $year, 'label' => ($year - 7).' EFY / '.$year]);
        }
        foreach (HistoricalPerformanceImport::distinct()->orderByDesc('year')->pluck('year') as $year) {
            $sources->put('history:'.$year, ['key' => 'history:'.$year, 'kind' => 'history', 'year' => (int) $year, 'efy' => (int) $year, 'gregorian' => $year + 7, 'label' => $year.' EFY / '.($year + 7)]);
        }

        return $sources;
    }

    private function sourceForYear(Collection $sources, int $year, bool $history): ?string
    {
        if (! $year) {
            return null;
        }
        $keys = $history ? ['history:'.$year, 'history:'.($year - 7), 'plan:'.$year, 'plan:'.($year + 7)] : ['plan:'.$year, 'plan:'.($year + 7)];
        foreach ($keys as $key) {
            if ($sources->has($key)) {
                return $key;
            }
        }

        return null;
    }

    private function options(Collection $entities): Collection
    {
        return $entities->map(fn ($e) => array_intersect_key($e, array_flip(['id', 'code', 'kpi', 'description', 'isParent', 'ancestors'])))->sortBy('code', SORT_NATURAL)->values();
    }

    private function selected(array $frame, string $type, ?string $entity): Collection
    {
        if ($type === 'target' && ! $entity) {
            return $frame['records'];
        }
        if (! in_array($type, ['objective', 'kpi', 'target'], true)) {
            return $frame['records'];
        }
        if (! $entity) {
            return collect();
        }
        $node = $frame['entities']->firstWhere('id', $entity);
        if (($node['measureType'] ?? null) === 'composite' && in_array($type, ['kpi', 'target'], true)) {
            return collect([$node]);
        }
        if (($node['measureType'] ?? null) === 'direct' && ($node['manualOverride'] ?? false)) {
            return collect([$node]);
        }
        if (in_array($type, ['objective', 'target'], true) && ($node['isParent'] ?? false)) {
            return $frame['records']->filter(fn ($r) => in_array($entity, $r['ancestors'], true))->values();
        }

        return $frame['records']->where('id', $entity)->values();
    }

    private function candidate(array $record, Collection $choices, ?Collection $referencePopulation = null, bool $requireStableIdentity = false): ?array
    {
        // The same authorized objective across periods/departments has explicit
        // identity even if its KPI code was reused on a different objective.
        $identical = $choices->where('id', $record['id']);
        if ($identical->count() === 1) {
            return $identical->first();
        }
        $linked = $choices->filter(fn ($r) => collect($record['links'])->contains('id', $r['id']) || collect($r['links'])->contains('id', $record['id']));
        if ($linked->count() === 1) {
            return $linked->first();
        }
        if ($requireStableIdentity && $linked->count() > 1) {
            return null;
        }
        if ($record['kpi'] !== '') {
            if ($referencePopulation && $referencePopulation->where('kpi', $record['kpi'])->count() !== 1) {
                return null;
            }
            $same = $choices->where('kpi', $record['kpi']);

            return $same->count() === 1 ? $same->first() : null;
        }
        if ($requireStableIdentity) {
            return null;
        }
        $same = $choices->filter(fn ($r) => $r['id'] === $record['id'] || mb_strtolower(trim($r['description'])) === mb_strtolower(trim($record['description'])));

        return $same->count() === 1 ? $same->first() : null;
    }

    public function pair(?array $reference, ?array $current, ?string $referencePeriod, string $currentPeriod, string $type): array
    {
        $status = 'pending';
        $reason = 'A corresponding indicator is unavailable on one side.';
        $delta = null;
        $componentPairs = [];
        if (($reference['measureType'] ?? null) === 'composite' && ($current['measureType'] ?? null) !== 'composite') {
            foreach ($reference['rollup']['components'] as $component) {
                $componentPairs[] = $this->pair($component, null, $referencePeriod, $currentPeriod, 'objective');
            }
        } elseif (($current['measureType'] ?? null) === 'composite' && ($reference['measureType'] ?? null) !== 'composite') {
            foreach ($current['rollup']['components'] as $component) {
                $componentPairs[] = $this->pair(null, $component, $referencePeriod, $currentPeriod, 'objective');
            }
        }
        if ($reference && $current) {
            $link = collect($reference['links'])->firstWhere('id', $current['id']) ?? collect($current['links'])->firstWhere('id', $reference['id']);
            $same = $type === 'target' || ($reference['kpi'] !== '' && $reference['kpi'] === $current['kpi']) || $reference['id'] === $current['id'] || mb_strtolower(trim($reference['description'])) === mb_strtolower(trim($current['description'])) || $link;
            $normalizer = app(HistoricalPerformanceWorkbook::class);
            $leftUnit = $normalizer->normalizeUnit($reference['unit']);
            $rightUnit = $normalizer->normalizeUnit($current['unit']);
            $conflictingCodes = $reference['kpi'] !== '' && $current['kpi'] !== '' && $reference['kpi'] !== $current['kpi'];
            $composite = ($reference['measureType'] ?? 'direct') === 'composite' || ($current['measureType'] ?? 'direct') === 'composite';
            $compositionReason = null;
            if ($composite && $reference['measureType'] === $current['measureType']) {
                [$componentPairs, $compositionReason] = $this->componentPairs($reference, $current, $referencePeriod, $currentPeriod);
            }
            if (! $same || $conflictingCodes || ($link && (! $link['comparable'] || ! in_array($link['type'], ['direct', 'renamed'], true)))) {
                $status = 'context';
                $reason = 'Different indicator definitions: side-by-side context only, not a like-for-like improvement.';
            } elseif ($link && $link['status'] !== 'approved') {
                $reason = 'The existing KPI relationship is awaiting review; source results remain visible.';
            } elseif ($composite && $reference['measureType'] !== $current['measureType']) {
                $status = 'context';
                $reason = 'Different measurement bases: weighted activity achievement and a directly measured KPI are not interchangeable.';
            } elseif ($composite && $compositionReason) {
                $status = 'context';
                $reason = $compositionReason;
            } elseif (! $composite && (! $leftUnit || ! $rightUnit || $leftUnit !== $rightUnit)) {
                $status = 'context';
                $reason = 'Measurement units are unspecified or different; no raw or achievement change is published.';
            } elseif (! $reference['directionConfirmed'] || ! $current['directionConfirmed'] || $reference['direction'] !== $current['direction']) {
                $reason = 'Confirm compatible achievement directions before comparing.';
            } elseif ($reference['scorable'] === false || $current['scorable'] === false) {
                $reason = 'Reference-only or excluded indicators are context, not scored improvement.';
            } elseif (($referencePeriod === 'annual') !== ($currentPeriod === 'annual') || str_starts_with((string) $referencePeriod, 'q') !== str_starts_with($currentPeriod, 'q')) {
                $reason = 'The selected periods have different lengths; annual and partial-period results are not like-for-like.';
            } elseif ($reference['achievement'] === null || $current['achievement'] === null) {
                $reason = 'Awaiting a target or reported result on one side. Missing values are not zero.';
            } elseif (! $reference['complete'] || ! $current['complete']) {
                $reason = 'Incomplete reporting period: available progress is shown, but no final improvement is calculated.';
            } else {
                $delta = round(($type === 'target' ? $current['rawAchievement'] : $current['achievement']) - $reference['achievement'], 2);
                $status = $delta > 0 ? 'improved' : ($delta < 0 ? 'declined' : 'unchanged');
                $reason = 'Compatible indicators, units, directions and complete reporting periods.';
            }
        }

        return ['reference' => $reference, 'current' => $current, 'status' => $status, 'reason' => $reason, 'delta' => $delta, 'componentPairs' => $componentPairs,
            'actualChange' => $delta === null || $current['actual'] === null || $reference['actual'] === null ? null : round($current['actual'] - $reference['actual'], 4),
            'targetChange' => $delta === null || $current['target'] === null || $reference['target'] === null ? null : round($current['target'] - $reference['target'], 4)];
    }

    private function componentPairs(array $reference, array $current, ?string $referencePeriod, string $currentPeriod): array
    {
        $left = collect($reference['rollup']['components']);
        $right = collect($current['rollup']['components']);
        $pairs = [];
        $used = [];
        $reason = $left->count() !== $right->count() ? 'Activity composition changed between these years. Both weighted scores are shown as context, not like-for-like improvement.' : null;
        foreach ($left as $record) {
            $match = $this->candidate($record, $right, $left, true);
            if ($match && in_array($match['id'], $used, true)) {
                $match = null;
            }
            if ($match) {
                $used[] = $match['id'];
                $link = collect($record['links'])->firstWhere('id', $match['id']) ?? collect($match['links'])->firstWhere('id', $record['id']);
                $normalizer = app(HistoricalPerformanceWorkbook::class);
                $unit = $normalizer->normalizeUnit($record['unit']);
                if (($link && ($link['status'] !== 'approved' || ! $link['comparable'] || ! in_array($link['type'], ['direct', 'renamed'], true)))
                    || ! $unit || $unit !== $normalizer->normalizeUnit($match['unit']) || $record['direction'] !== $match['direction']) {
                    $reason ??= 'Component definitions, units, directions or relationships need review before claiming like-for-like improvement.';
                }
                $leftWeight = $reference['rollup']['weight'];
                $rightWeight = $current['rollup']['weight'];
                if ($leftWeight <= 0 || $rightWeight <= 0 || $record['weight'] === null || $match['weight'] === null
                    || abs($record['weight'] / $leftWeight - $match['weight'] / $rightWeight) > .00001) {
                    $reason = 'Component weights changed or are incomplete. Both weighted scores are shown as context, not like-for-like improvement.';
                }
            } else {
                $reason ??= 'Activity composition or approved component relationships changed. Both weighted scores are shown as context, not like-for-like improvement.';
            }
            $pairs[] = $this->pair($record, $match, $referencePeriod, $currentPeriod, 'objective');
        }
        foreach ($right->reject(fn ($r) => in_array($r['id'], $used, true)) as $record) {
            $pairs[] = $this->pair(null, $record, $referencePeriod, $currentPeriod, 'objective');
        }

        return [$pairs, $reason];
    }

    private function available(Collection $rows, string $side): ?float
    {
        $values = $rows->pluck($side)->filter()->unique('id')->pluck('achievement')->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round((float) $values->avg(), 2);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
