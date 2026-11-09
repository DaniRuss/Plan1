<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** One department's overall annual performance across independent fiscal years. */
class AnnualDepartmentComparison
{
    public function __construct(private readonly DashboardWorkspace $workspace, private readonly AnnualDepartmentEvidence $evidence, private readonly EdrPerformanceCalculator $calculator, private readonly PerformanceLevel $ratings) {}

    public function build(User $user, Request $request, Collection $sources, Collection $departments): array
    {
        if (! $this->workspace->organizationWide($user)) {
            throw ValidationException::withMessages([$request->has('comparison_type') ? 'comparison_type' : 'comparison_method' => 'Department comparison requires organization-wide access.']);
        }
        $input = $request->validate([
            'current_source' => ['nullable', Rule::in($sources->keys()->all())],
            'reference_source' => ['nullable', Rule::in($sources->keys()->all())],
            'comparison_view' => 'nullable|in:master,department',
            'performance_year' => 'nullable|integer|min:1900|max:9999',
            'reference_year' => 'nullable|integer|min:1900|max:9999',
            'current_department' => ['nullable', 'integer', Rule::in($departments->pluck('id')->all())],
            'reference_department' => ['nullable', 'integer', Rule::in($departments->pluck('id')->all())],
            'performance_department' => ['nullable', 'integer', Rule::in($departments->pluck('id')->all())],
        ]);
        $legacyYear = (int) $request->get('performance_year', 0);
        $legacyKey = collect(['plan:'.$legacyYear, 'plan:'.($legacyYear + 7), 'history:'.$legacyYear, 'history:'.($legacyYear - 7)])->first(fn ($key) => $legacyYear && $sources->has($key));
        if (empty($input['current_source']) && $legacyYear && $legacyKey === null) {
            throw ValidationException::withMessages(['performance_year' => 'No authorized annual source is available for the selected fiscal year.']);
        }
        $key = $input['current_source'] ?? $legacyKey ?? $sources->where('kind', 'plan')->sortByDesc('year')->keys()->first() ?? $sources->keys()->first();
        $source = $sources->get($key);
        $referenceYear = (int) ($input['reference_year'] ?? 0);
        $referenceKey = $input['reference_source'] ?? ($referenceYear ? $sources->first(fn ($s) => $s['gregorian'] === $referenceYear || $s['efy'] === $referenceYear)['key'] ?? null : null);
        if (! $referenceKey && $referenceYear) {
            throw ValidationException::withMessages(['reference_year' => 'No authorized annual source is available for the reference fiscal year.']);
        }
        $referenceKey ??= $sources->where('gregorian', '<', $source['gregorian'])->sortByDesc('gregorian')->keys()->first()
            ?? $sources->first(fn ($s) => $s['gregorian'] !== $source['gregorian'])['key'] ?? null;
        $referenceSource = $referenceKey ? $sources->get($referenceKey) : null;
        if ($referenceSource && $referenceSource['gregorian'] === $source['gregorian']) {
            throw ValidationException::withMessages(['reference_source' => 'Select two different fiscal years for the same department. These labels refer to the same fiscal year.']);
        }
        $current = isset($input['current_department']) ? (int) $input['current_department'] : (isset($input['performance_department']) ? (int) $input['performance_department'] : null);
        $reference = isset($input['reference_department']) ? (int) $input['reference_department'] : null;
        if ($current && $reference && $current !== $reference) {
            throw ValidationException::withMessages(['reference_department' => 'Department comparison compares one department with itself across two fiscal years. Select the same department.']);
        }
        $data = $this->evidence->load($user, $source, $departments);
        $referenceData = $referenceSource ? $this->evidence->load($user, $referenceSource, $departments) : null;
        $withCurrentEvidence = $departments->sortByDesc(fn ($d) => $data['byDepartment']->get($d->id, collect())->where('eligible', true)->count())->first(fn ($d) => $data['byDepartment']->get($d->id, collect())->where('eligible', true)->isNotEmpty());
        $current ??= $reference ?? $withCurrentEvidence?->id ?? ($referenceData ? $departments->sortByDesc(fn ($d) => $referenceData['byDepartment']->get($d->id, collect())->where('eligible', true)->count())->first()?->id : $departments->first()?->id);
        $reference = $current;
        $department = $departments->firstWhere('id', $current);
        $right = $department ? $this->score($department, $data['byDepartment']->get($current, collect())) : null;
        $left = $department && $referenceData ? $this->score($department, $referenceData['byDepartment']->get($current, collect())) : null;
        $notice = ! $department ? 'No authorized active department is available.' : (! $referenceSource ? 'A second fiscal-year source is not available yet. No year is compared with itself.' : null);
        $delta = $left && $right && $left['score'] !== null && $right['score'] !== null ? round($right['score'] - $left['score'], 2) : null;
        $view = $input['comparison_view'] ?? ($request->get('comparison_method') === 'department' ? 'department' : 'master');
        $builder = ['type' => 'department', 'view' => $view, 'types' => ComparisonWorkbench::TYPES, 'sources' => $sources, 'departments' => $departments, 'wide' => true, 'finalOnly' => true,
            'frequency' => 'all', 'scope' => 'official', 'periods' => ['annual' => 'Full fiscal year'], 'currentSource' => $source, 'referenceSource' => $source,
            'currentPeriod' => 'annual', 'referencePeriod' => 'annual', 'currentDepartment' => $current, 'referenceDepartment' => $reference,
            'currentEntity' => null, 'referenceEntity' => null, 'currentOptions' => collect(), 'referenceOptions' => collect()];
        $builder['referenceSource'] = $referenceSource;
        $annualRows = collect(['reference' => $left, 'current' => $right])->filter()->map(fn ($card, $side) => $card + ['side' => $side, 'sourceLabel' => $side === 'reference' ? $referenceSource['label'] : $source['label'], 'filename' => $side === 'reference' ? $referenceData['filename'] : $data['filename']])->values();
        $warnings = collect(['reference' => $referenceData, 'current' => $data])->filter()->flatMap(fn ($frame, $side) => $frame['attributionWarnings']->map(fn ($w) => $w + ['side' => $side, 'filename' => $frame['filename']]));

        return ['workspace' => 'departments', 'method' => 'department', 'activeView' => $view, 'builder' => $builder, 'departmentRows' => collect($right ? [$right] : []), 'annualRows' => $annualRows, 'rows' => $annualRows,
            'year' => $source['gregorian'], 'referenceYear' => $referenceSource['gregorian'] ?? null, 'sourceFilename' => $data['filename'], 'sourceLabel' => $source['label'], 'attributionWarnings' => $warnings,
            'organizationWide' => true, 'departments' => $departments, 'scope' => 'official', 'frequency' => 'all', 'movement' => 'all', 'sort' => 'code', 'search' => '', 'department' => $current, 'referenceDepartment' => $reference,
            'currentLabel' => $source['label'], 'referenceLabel' => $referenceSource['label'] ?? 'Choose a reference year',
            'currentCard' => $right, 'referenceCard' => $left, 'delta' => $delta, 'currentAverage' => $right['score'] ?? null, 'referenceAverage' => $left['score'] ?? null,
            'populationChanged' => $left && $right && $left['expected'] && $right['expected'] && $this->population($left) !== $this->population($right),
            'selectionNotice' => $notice, 'matched' => $delta !== null ? 1 : 0, 'unmatched' => $delta === null ? 1 : 0];
    }

    private function population(array $card): array
    {
        return $card['activities']->where('eligible', true)->map(fn ($r) => [
            'indicator' => $r['kpi'] ?: mb_strtolower(trim($r['description'])), 'unit' => app(HistoricalPerformanceWorkbook::class)->normalizeUnit($r['unit']),
            'share' => $r['weightShare'],
        ])->sortBy(fn ($r) => json_encode($r))->values()->all();
    }

    private function score($department, Collection $activities): array
    {
        $eligible = $activities->where('eligible', true);
        $reviewed = $eligible->where('reviewed', true);
        $missingWeights = $eligible->filter(fn ($r) => $r['weight'] === null || $r['weight'] < 0)->count();
        $totalWeight = (float) $eligible->sum(fn ($r) => max(0, (float) $r['weight']));
        $reviewedWeight = (float) $reviewed->sum(fn ($r) => max(0, (float) $r['weight']));
        $complete = $eligible->isNotEmpty() && $reviewed->count() === $eligible->count() && ! $missingWeights && $totalWeight > 0;
        $summary = $this->calculator->summary($reviewed->map(fn ($r) => ['_parent_item_id' => null, 'weighted_score' => min(100, max(0, $r['achievement'])) / 100 * max(0, (float) $r['weight']), 'score_weight' => max(0, (float) $r['weight'])])->all());
        $score = $complete ? $summary['achievement'] : null;
        $raw = $complete ? round((float) $reviewed->sum(fn ($r) => $r['rawAchievement'] * $r['weight']) / $totalWeight, 2) : null;
        $hasProgress = $eligible->contains(fn ($r) => $r['actual'] !== null || $r['achievement'] !== null);
        $state = $eligible->isEmpty() ? 'no_data' : ($complete ? 'complete' : ($missingWeights || $eligible->contains(fn ($r) => in_array($r['status'], ['shared', 'duplicate', 'source_review'], true)) ? 'needs_review' : ($totalWeight <= 0 ? 'no_weight' : ($hasProgress ? 'partial' : 'awaiting'))));
        $activities = $activities->map(fn ($r) => array_replace($r, ['weightShare' => $r['eligible'] && ! $missingWeights && $totalWeight > 0 ? round((float) $r['weight'] / $totalWeight * 100, 2) : null,
            'contribution' => $r['eligible'] && $r['reviewed'] && $r['weight'] !== null && $r['weight'] >= 0 ? round($r['achievement'] / 100 * $r['weight'], 4) : null]));

        return ['id' => $department->id, 'name' => $department->name, 'code' => $department->code, 'score' => $score, 'rawScore' => $raw, 'rank' => null, 'rating' => $score === null ? null : $this->ratings->for($score),
            'expected' => $eligible->count(), 'reviewed' => $reviewed->count(), 'reportingCoverage' => $eligible->isEmpty() ? 0.0 : round($reviewed->count() / $eligible->count() * 100, 2),
            'weightCoverage' => $missingWeights || $totalWeight <= 0 ? null : round($reviewedWeight / $totalWeight * 100, 2), 'totalWeight' => $totalWeight, 'missingWeights' => $missingWeights,
            'state' => $state, 'activities' => $activities];
    }
}
