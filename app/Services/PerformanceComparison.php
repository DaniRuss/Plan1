<?php

namespace App\Services;

use App\Models\{User, Department, WeeklyPerformance, PeriodicPerformance};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerformanceComparison
{
    public function __construct(private readonly DashboardWorkspace $workspace, private readonly EdrPerformanceCalculator $calculator) {}

    public function build(User $user, Request $request): array
    {
        $months = ['july','august','september','october','november','december','january','february','march','april','may','june'];
        $periods = ['all'=>'All monthly results','q1'=>'Quarter 1 (July–September)','q2'=>'Quarter 2 (October–December)','q3'=>'Quarter 3 (January–March)','q4'=>'Quarter 4 (April–June)','annual'=>'Annual records'];
        foreach ($months as $month) $periods[$month] = ucfirst($month);
        $organizationWide = $this->workspace->organizationWide($user);
        $departments = Department::where('is_active', true)->when(!$organizationWide, fn ($q) => $q->whereIn('id', array_filter([$user->department_id ?? $user->employee?->department_id])))->orderBy('name')->get(['id','name']);
        $years = $this->workspace->items($user)->join('plans','plans.id','=','plan_items.plan_id')->distinct()->orderByDesc('plans.year')->pluck('plans.year')->map(fn ($year) => (int) $year);
        $finalOnly = $user->isHeadOffice() && !$user->isSystemAdmin() && !$user->isPlanReportHeadOffice();
        $input = $request->validate([
            'comparison_method'=>['nullable', Rule::in(['year','period','department','target'])],
            'performance_year'=>'nullable|integer|min:1900|max:9999', 'reference_year'=>'nullable|integer|min:1900|max:9999',
            'performance_period'=>['nullable',Rule::in(array_keys($periods))], 'reference_period'=>['nullable',Rule::in(array_keys($periods))],
            'performance_department'=>['nullable','integer',Rule::in($departments->pluck('id')->all())],
            'reference_department'=>['nullable','integer',Rule::in($departments->pluck('id')->all())],
            'performance_kpi'=>'nullable|string|max:1000',
            'reporting_frequency'=>'nullable|in:all,daily,weekly,monthly',
            'result_scope'=>['nullable',Rule::in($finalOnly ? ['official'] : ['official','reported'])],
            'movement'=>'nullable|in:all,improved,declined,unchanged,unmatched', 'comparison_sort'=>'nullable|in:change,achievement,name',
        ]);
        $method = $input['comparison_method'] ?? 'year';
        $year = (int) ($input['performance_year'] ?? $years->first() ?? now()->year);
        $referenceYear = $method === 'year' ? (int) ($input['reference_year'] ?? $year - 1) : $year;
        $period = $input['performance_period'] ?? 'all';
        $referencePeriod = $method === 'period' ? ($input['reference_period'] ?? 'all') : $period;
        $department = $input['performance_department'] ?? null;
        $referenceDepartment = $method === 'department' ? ($input['reference_department'] ?? null) : $department;
        if ($method === 'department' && (!$organizationWide || !$department || !$referenceDepartment)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['comparison_method'=>'Choose two authorized departments for a department comparison.']);
        }
        $frequency = $input['reporting_frequency'] ?? 'all';
        $scope = $input['result_scope'] ?? 'official';
        $current = $this->rows($user, $year, $department ? [(int) $department] : $departments->pluck('id')->all(), $period, $frequency, $scope, $months);
        if ($method === 'target') $current = $current->map(fn ($row) => array_replace($row, ['achievement'=>$row['rawAchievement']]));
        $reference = $method === 'target' ? collect() : $this->rows($user, $referenceYear, $referenceDepartment ? [(int) $referenceDepartment] : $departments->pluck('id')->all(), $referencePeriod, $frequency, $scope, $months);
        if (in_array($method,['year','department'],true)) {
            $commonPeriods = [];
            foreach ($current->groupBy('key') as $key=>$group) {
                $commonPeriods[$key] = $group->pluck('period')->intersect($reference->where('key',$key)->pluck('period'))->all();
            }
            $current = $current->filter(fn ($row) => empty($commonPeriods[$row['key']]) || in_array($row['period'],$commonPeriods[$row['key']],true));
            $reference = $reference->filter(fn ($row) => empty($commonPeriods[$row['key']]) || in_array($row['period'],$commonPeriods[$row['key']],true));
        }
        $kpis = $current->pluck('objective')->merge($reference->pluck('objective'))->unique()->sort()->values();
        $kpi = $input['performance_kpi'] ?? null;
        if ($kpi) { $current = $current->where('objective',$kpi); $reference = $reference->where('objective',$kpi); }
        // Include unit and direction in the match key: identically named but
        // differently measured KPIs must not be presented as a valid pair.
        $summarize = fn ($rows) => $rows->groupBy('key')->map(fn ($group) => [
            'objective'=>$group->first()['objective'], 'unit'=>$group->first()['unit'],
            'achievement'=>round((float) $group->avg('achievement'),2), 'records'=>$group->sum('records'),
            'periods'=>$group->pluck('period')->unique()->all(),
            'actual'=>$group->count() === 1 ? $group->first()['actual'] : null,
            'target'=>$group->count() === 1 ? $group->first()['target'] : null,
        ]);
        $a = $summarize($current); $b = $summarize($reference);
        $rows = $a->keys()->merge($b->keys())->unique()->map(function ($key) use ($a,$b,$method) {
            $left = $a->get($key); $right = $b->get($key);
            $current = $left['achievement'] ?? null;
            $baseline = $method === 'target' ? 100.0 : ($right['achievement'] ?? null);
            $delta = $current === null || $baseline === null ? null : round($current - $baseline,2);
            if (in_array($method,['year','department'],true) && !array_intersect($left['periods'] ?? [],$right['periods'] ?? [])) $delta = null;
            return ['objective'=>($left ?? $right)['objective'], 'unit'=>($left ?? $right)['unit'], 'current'=>$current, 'reference'=>$baseline,
                'actual'=>$left['actual'] ?? null, 'target'=>$left['target'] ?? null,
                'delta'=>$delta, 'direction'=>$delta === null ? 'unmatched' : ($delta > 0 ? 'improved' : ($delta < 0 ? 'declined' : 'unchanged')),
                'currentRecords'=>$left['records'] ?? 0, 'referenceRecords'=>$right['records'] ?? 0];
        })->values();
        $movement = $input['movement'] ?? 'all';
        if ($movement !== 'all') $rows = $rows->where('direction',$movement)->values();
        $sort = $input['comparison_sort'] ?? 'change';
        $rows = match ($sort) { 'name'=>$rows->sortBy('objective',SORT_NATURAL|SORT_FLAG_CASE), 'achievement'=>$rows->sortByDesc(fn ($row) => $row['current'] ?? -1), default=>$rows->sortByDesc(fn ($row) => $row['delta'] === null ? -1 : abs($row['delta'])) };
        $rows = $rows->values();
        $matched = $rows->whereNotNull('delta');
        $currentLabel = $year.' · '.$periods[$period].($department ? ' · '.$departments->firstWhere('id',$department)?->name : '');
        $referenceLabel = $method === 'target' ? 'Target achievement (100%)' : $referenceYear.' · '.$periods[$referencePeriod].($referenceDepartment ? ' · '.$departments->firstWhere('id',$referenceDepartment)?->name : '');
        return compact('method','year','referenceYear','period','referencePeriod','department','referenceDepartment','frequency','scope','movement','sort','kpi','years','periods','departments','organizationWide','finalOnly','rows','kpis','currentLabel','referenceLabel') + [
            'matched'=>$matched->count(),'unmatched'=>$rows->whereNull('delta')->count(),
            'currentAverage'=>$matched->isEmpty() ? null : round((float) $matched->avg('current'),2),
            'referenceAverage'=>$matched->isEmpty() ? null : round((float) $matched->avg('reference'),2),
            'delta'=>$matched->isEmpty() ? null : round((float) $matched->avg('delta'),2),
        ];
    }

    private function rows(User $user, int $year, array $departments, string $period, string $frequency, string $scope, array $months)
    {
        $items = $this->workspace->items($user,$year,$departments)->when($frequency !== 'all', fn ($q) => $q->forReportingFrequency($frequency))
            ->with(['plan.department','strategicObjective'])->get();
        $ids = $items->pluck('id');
        $weekly = WeeklyPerformance::whereIn('plan_item_id',$ids)->where('year',$year)->get()->filter(fn ($row) => $scope === 'reported' || $this->workspace->isOfficial($row))->groupBy('plan_item_id');
        $periodic = PeriodicPerformance::whereIn('plan_item_id',$ids)->get()->filter(fn ($row) => $scope === 'reported' || $this->workspace->isOfficial($row))->groupBy('plan_item_id');
        $quarters = ['q1'=>array_slice($months,0,3),'q2'=>array_slice($months,3,3),'q3'=>array_slice($months,6,3),'q4'=>array_slice($months,9,3)];
        $allowed = $period === 'all' ? $months : ($quarters[$period] ?? [$period]);
        $rows = collect();
        $parentIds = $items->pluck('strategicObjective.parent_id')->filter()->map(fn ($id) => (int) $id);
        foreach ($items as $item) {
            $objective = $item->strategicObjective;
            if (!$objective || $objective->is_reference_only || $objective->exclude_from_overall_score || $parentIds->contains((int) $objective->id)) continue;
            $aggregated = in_array($item->effectiveReportingFrequency(),['weekly','daily'],true);
            $groups = $aggregated ? ($weekly->get($item->id,collect())->groupBy(fn ($row) => strtolower($row->month)))
                : $periodic->get($item->id,collect())->groupBy(fn ($row) => preg_replace('/^\d{4}-/','',strtolower($row->period)));
            // Explicit quarter results are used only when no monthly results exist
            // for that quarter, preventing the same result being counted twice.
            if (isset($quarters[$period]) && !$groups->keys()->intersect($allowed)->count() && $groups->has($period)) $allowedForItem = [$period];
            else $allowedForItem = $allowed;
            foreach ($groups as $part => $entries) {
                if (!in_array($part,$allowedForItem,true)) continue;
                $target = $item->getPlannedTargetForPeriod($part);
                if ($target === null) continue;
                if ($aggregated) {
                    $copy = clone $item;
                    $copy->setRelation('weeklyPerformances',$entries->map(function ($row) { $copy = clone $row; $copy->workflow_status='approved_by_head_office'; return $copy; })->values());
                    $actual = (float) $copy->getActualValueForPeriod($part);
                } else {
                    $entry = $entries->first(fn ($row) => strtolower($row->period) === "$year-$part") ?? $entries->first(fn ($row) => strtolower($row->period) === $part);
                    if (!$entry) continue;
                    $actual = (float) $entry->actual_value;
                }
                $rows->push(['key'=>json_encode([$objective->description,$item->unit ?: $objective->unit,$objective->goal_direction ?? 'increase']),
                    'objective'=>$objective->description,'unit'=>$item->unit ?: $objective->unit,'period'=>$part,'actual'=>$actual,'target'=>(float)$target,
                    'achievement'=>$this->calculator->achievement($actual,(float)$target,$objective->goal_direction ?? 'increase'),
                    'rawAchievement'=>$this->calculator->rawAchievement($actual,(float)$target,$objective->goal_direction ?? 'increase'),'records'=>$aggregated ? $entries->count() : 1]);
            }
        }
        return $rows;
    }
}
