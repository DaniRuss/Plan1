<?php

namespace App\Services;

use Illuminate\Support\Collection;

/** Derived scorecard evidence only. Never persists or invents a physical KPI actual. */
class ComparisonCompositeRollup
{
    public function apply(Collection $entities, Collection $records): array
    {
        $all = $entities->keyBy('id')->replace($records->keyBy('id'));
        $all = $all->map(fn ($record) => $record + ['measureType' => 'direct', 'rollup' => null]);
        $rollups = [];
        foreach ($all as $id => $parent) {
            if (! ($parent['compositeCandidate'] ?? false)) {
                continue;
            }
            $descendants = $all->filter(fn ($r) => in_array($id, $r['ancestors'], true));
            $components = $descendants->filter(function ($record) use ($descendants, $all, $id) {
                if (! $record['scorable'] || ! ($record['includeInRollup'] ?? true)) {
                    return false;
                }
                foreach ($record['ancestors'] as $ancestor) {
                    if ($ancestor === $id) {
                        break;
                    }
                    $branch = $all->get($ancestor);
                    if ($branch && (! $branch['scorable'] || ! ($branch['includeInRollup'] ?? true) || ! ($branch['compositeCandidate'] ?? false))) {
                        return false;
                    }
                }

                return ! ($record['compositeCandidate'] ?? false);
            })->sortBy('code', SORT_NATURAL)->values();
            $warnings = [];
            $weight = 0.0;
            $score = 0.0;
            $rawScore = 0.0;
            $completed = 0;
            $scored = 0;
            $components = $components->map(function ($record) use (&$weight, &$score, &$rawScore, &$completed, &$scored, &$warnings) {
                $validWeight = $record['weight'] !== null && is_numeric($record['weight']) && $record['weight'] >= 0;
                $ready = ($record['sourceComplete'] ?? $record['complete']) && $record['achievement'] !== null;
                if (! $validWeight) {
                    $warnings[] = 'An activity weight is missing or invalid.';
                } else {
                    $weight += (float) $record['weight'];
                }
                $contribution = $validWeight && $ready ? (float) $record['weight'] * min(100, max(0, $record['achievement'])) / 100 : null;
                if ($contribution !== null) {
                    $score += $contribution;
                    $rawScore += (float) $record['weight'] * max(0, $record['rawAchievement']) / 100;
                    $scored++;
                }
                if ($ready) {
                    $completed++;
                }

                return array_replace($record, ['contribution' => $contribution === null ? null : round($contribution, 6), 'componentComplete' => $ready]);
            });
            $declaredWeight = $parent['weight'];
            if ($declaredWeight !== null && abs((float) $declaredWeight - $weight) > .0001) {
                $warnings[] = 'Component weights do not reconcile with the objective weight. The selection may cover only part of this objective.';
            }
            if ($components->isEmpty() || $weight <= 0) {
                $warnings[] = 'No usable weighted component set is available.';
            }
            $complete = $components->isNotEmpty() && $completed === $components->count() && $warnings === [];
            $achievement = $complete ? round($score / $weight * 100, 2) : null;
            $raw = $complete ? round($rawScore / $weight * 100, 2) : null;
            $rollup = ['weight' => round($weight, 6), 'declaredWeight' => $declaredWeight, 'score' => $scored ? round($score, 6) : null,
                'completed' => $completed, 'expected' => $components->count(), 'components' => $components->all(), 'warnings' => array_values(array_unique($warnings))];
            $rollups[$id] = array_replace($parent, ['measureType' => 'composite', 'rollup' => $rollup, 'target' => null, 'actual' => null,
                'unit' => null, 'direction' => 'increase', 'directionConfirmed' => true, 'achievement' => $achievement, 'rawAchievement' => $raw,
                'complete' => $complete, 'completed' => $completed, 'expected' => $components->count(),
                'reason' => $complete ? 'Weighted achievement calculated from component activities.' : 'Incomplete component evidence or weights. Available points are provisional.']);
        }
        $all = $all->replace($rollups);
        // Flat cohort summaries use terminal evidence once. Composite headlines
        // are selected explicitly from entities, never added to their own detail.
        $records = $records->map(fn ($r) => $all->get($r['id']))->reject(fn ($r) => $r['measureType'] === 'composite')->values();
        $directContainers = $records->filter(fn ($r) => $r['isParent'] && $r['kpi'] !== '')->pluck('id');
        $records = $records->reject(fn ($r) => $directContainers->intersect($r['ancestors'])->isNotEmpty())->values();

        return ['entities' => $all->values(), 'records' => $records];
    }
}
