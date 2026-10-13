<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\StrategicObjective;
use App\Services\ObjectiveWorkbookImport;
use App\Services\ObjectivePerformerMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ObjectiveWorkbookImportController extends Controller
{
    public function upload(Request $request, ObjectiveWorkbookImport $parser)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240', 'target_year' => 'required|integer|between:2000,2200']);
        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs('objective-imports', $token.'.'.$request->file('file')->extension(), 'local');
        try {
            $rows = $parser->parse(Storage::disk('local')->path($path));
            if (!$rows) throw new \InvalidArgumentException('No objective rows were found.');
            Storage::disk('local')->put('objective-imports/'.$token.'.json', json_encode([
                'user_id' => auth()->id(), 'target_year' => (int) $request->target_year,
                'filename' => $request->file('file')->getClientOriginalName(), 'rows' => $rows,
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        return redirect()->route('objectives.import.review', $token);
    }

    public function review(Request $request, string $token, ObjectivePerformerMatcher $matcher)
    {
        $batch = $this->batch($token);
        $departments = Department::orderBy('name')->get();
        $performerMatches = array_map(fn ($row) => $matcher->match($row['performer_text'], $departments), $batch['rows']);
        $importDefaults = $this->buildHierarchyDefaults($batch['rows'], $performerMatches);
        $existing = StrategicObjective::whereIn('code', collect($batch['rows'])->pluck('code')->filter())
            ->get(['code', 'status'])->keyBy('code');
        $duplicates = collect($batch['rows'])->pluck('code')->filter()->countBy()->filter(fn ($count) => $count > 1);
        return view('objectives.import-review', compact('batch', 'departments', 'performerMatches', 'importDefaults', 'existing', 'duplicates', 'token'));
    }

    public function commit(Request $request, string $token, ObjectivePerformerMatcher $matcher)
    {
        $batch = $this->batch($token);
        $rows = $batch['rows'];
        $departmentModels = Department::all();
        $departments = $departmentModels->pluck('id')->map(fn ($id) => (int) $id)->all();
        $codes = [];
        $errors = [];
        $prepared = [];
        foreach ($rows as $index => $row) {
            $input = $request->input('rows.'.$index, []);
            if (($input['action'] ?? '') === 'skip') continue;
            $code = trim((string) ($input['code'] ?? $row['code']));
            if (!preg_match('/^\d+(?:\.\d+){0,3}$/', $code)) $errors[] = "Row {$row['source_row']}: enter a valid code (up to four levels), or skip.";
            if (isset($codes[$code])) $errors[] = "Rows {$codes[$code]} and {$row['source_row']}: duplicate code {$code}.";
            $codes[$code] = $row['source_row'];
            $match = $matcher->match($row['performer_text'], $departmentModels);
            $weight = $input['weight'] ?? $row['weight'];
            if ($weight !== null && $weight !== '' && (!is_numeric($weight) || (float) $weight < 0)) $errors[] = "Row {$row['source_row']}: weight is invalid.";
            $performers = array_values(array_unique(array_map('intval', $input['performers'] ?? $match['ids'])));
            $departmentId = (int) ($input['department_id'] ?? ($performers[0] ?? 0));
            $direction = $input['goal_direction'] ?? 'increase';
            $weekly = $input['weekly_plan_mode'] ?? 'distribute';
            $frequency = strtolower(trim((string) ($input['reporting_frequency'] ?? $row['reporting_frequency'] ?? '')));
            if (!in_array($frequency, ['', 'inherit', 'daily', 'weekly', 'monthly'], true)) $errors[] = "Row {$row['source_row']}: invalid reporting frequency.";
            $frequency = in_array($frequency, ['', 'inherit'], true) ? null : $frequency;
            if ($frequency) abort_unless(auth()->user()->canManageObjectiveFrequency(), 403);
            $distribution = $input['target_distribution'] ?? 'checkpoint';
            if (!in_array($direction, ['increase', 'decrease'], true) || !in_array($weekly, ['distribute', 'repeat'], true) || !in_array($distribution, ['sum', 'checkpoint', 'manual'], true)) $errors[] = "Row {$row['source_row']}: invalid calculation setting.";
            $existing = StrategicObjective::where('code', $code)->first();
            if ($existing) $errors[] = "Row {$row['source_row']}: code {$code} already exists ({$existing->status}). Existing objectives cannot be overwritten by this import.";
            $prepared[$code] = compact('row', 'code', 'departmentId', 'direction', 'weekly', 'frequency', 'distribution', 'weight', 'performers', 'match', 'input');
        }
        foreach ($prepared as $code => $item) {
            if (!str_contains($code, '.')) continue;
            $parentCode = substr($code, 0, strrpos($code, '.'));
            if (!isset($prepared[$parentCode])) $errors[] = "Row {$item['row']['source_row']}: parent {$parentCode} must be included in this batch.";
        }
        // Resolve the hierarchy from the deepest rows upward. Blank parent
        // weights become the sum of direct children, and parent performers
        // inherit every department assigned anywhere below them.
        uksort($prepared, fn ($a, $b) => substr_count($b, '.') <=> substr_count($a, '.'));
        foreach (array_keys($prepared) as $code) {
            $code = (string) $code;
            $children = array_filter($prepared, fn ($item) => $this->parentCode($item['code']) === $code);
            $hasChildren = $children !== [];
            if ($prepared[$code]['row']['weight'] === null && $hasChildren) {
                $missingChild = collect($children)->first(fn ($child) => $child['weight'] === null || $child['weight'] === '');
                if ($missingChild) {
                    $errors[] = "Row {$prepared[$code]['row']['source_row']}: weight cannot roll up because child {$missingChild['code']} has no weight.";
                } else {
                    $prepared[$code]['weight'] = array_sum(array_map(fn ($child) => (float) $child['weight'], $children));
                    $prepared[$code]['isCalculatedWeight'] = true;
                }
            } elseif ($prepared[$code]['row']['weight'] === null) {
                $prepared[$code]['weight'] = 0;
                $prepared[$code]['isReferenceOnly'] = true;
            }
            if ($hasChildren) {
                foreach ($children as $child) {
                    foreach ($child['performers'] as $performer) {
                        if (!in_array($performer, $prepared[$code]['performers'], true)) $prepared[$code]['performers'][] = $performer;
                    }
                }
                if (!$prepared[$code]['departmentId']) $prepared[$code]['departmentId'] = $prepared[$code]['performers'][0] ?? 0;
            }
            if (array_diff($prepared[$code]['performers'], $departments)) $errors[] = "Row {$prepared[$code]['row']['source_row']}: contains an invalid performer selection.";
            if (!$prepared[$code]['departmentId'] || !in_array($prepared[$code]['departmentId'], $prepared[$code]['performers'], true)) {
                $prepared[$code]['departmentId'] = $prepared[$code]['performers'][0] ?? 0;
            }
            if (!in_array($prepared[$code]['departmentId'], $departments, true)) $errors[] = "Row {$prepared[$code]['row']['source_row']}: no existing department could be assigned.";
            if ($prepared[$code]['match']['unmatched'] && !$hasChildren && empty($prepared[$code]['input']['confirm_unmatched'])) {
                $errors[] = "Row {$prepared[$code]['row']['source_row']}: review unmatched performer names (".implode(', ', $prepared[$code]['match']['unmatched']).") and confirm the selection.";
            }
        }
        uksort($prepared, fn ($a, $b) => substr_count($a, '.') <=> substr_count($b, '.') ?: strnatcasecmp($a, $b));
        $childWeights = [];
        foreach ($prepared as $code => $item) {
            if (!str_contains($code, '.')) continue;
            $parentCode = substr($code, 0, strrpos($code, '.'));
            $childWeights[$parentCode] = ($childWeights[$parentCode] ?? 0) + (float) ($item['weight'] ?? 0);
        }
        foreach ($childWeights as $parentCode => $total) {
            if (isset($prepared[$parentCode]) && $total > (float) $prepared[$parentCode]['weight'] + 0.001) {
                $errors[] = "Children of {$parentCode} total {$total}, above parent weight {$prepared[$parentCode]['weight']}. Correct weights before import.";
            }
        }
        if ($errors) throw ValidationException::withMessages(['import' => $errors]);

        DB::transaction(function () use ($prepared, $batch) {
            $created = [];
            foreach ($prepared as $code => $item) {
                $row = $item['row'];
                $parentCode = str_contains($code, '.') ? substr($code, 0, strrpos($code, '.')) : null;
                $data = array_intersect_key($row, array_flip(array_merge(['description', 'weight', 'unit', 'baseline_value', 'annual_target'], array_keys(ObjectiveWorkbookImport::PERIOD_COLUMNS))));
                $data['code'] = $code;
                $data['target_year'] = $batch['target_year'];
                $data['baseline_year'] = (string) ($batch['target_year'] - 1);
                $data['department_id'] = $item['departmentId'];
                $data['lead_department_id'] = $item['departmentId'];
                $data['performers'] = $item['performers'];
                $data['weight'] = (float) $item['weight'];
                $data['is_calculated_weight'] = (bool) ($item['isCalculatedWeight'] ?? false);
                $data['is_reference_only'] = (bool) ($item['isReferenceOnly'] ?? false);
                $data['exclude_from_overall_score'] = (bool) ($item['isReferenceOnly'] ?? false);
                $data['company_consolidation_method'] = 'lead_department';
                $data['goal_direction'] = $item['direction'];
                $data['weekly_plan_mode'] = $item['weekly'];
                $data['reporting_frequency'] = $item['frequency'];
                $data['target_distribution'] = $item['distribution'];
                $data['parent_id'] = $parentCode ? $created[$parentCode]->id : null;
                $data['owner_id'] = auth()->id();
                $data['status'] = 'draft';
                $data['is_active'] = true;
                $data['include_in_parent_rollup'] = !($item['isReferenceOnly'] ?? false);
                $data['data_source'] = 'Workbook: '.$batch['filename'].', row '.$row['source_row'];
                $created[$code] = StrategicObjective::create($data);
            }
        });
        Storage::disk('local')->delete('objective-imports/'.$token.'.json');
        return redirect()->route('objectives.index')->with('success', count($prepared).' objectives imported as drafts. Review and submit them through the normal approval workflow.');
    }

    private function batch(string $token): array
    {
        abort_unless(Str::isUuid($token), 404);
        $path = 'objective-imports/'.$token.'.json';
        abort_unless(Storage::disk('local')->exists($path), 404);
        $batch = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(($batch['user_id'] ?? null) === auth()->id(), 403);
        return $batch;
    }

    private function parentCode(string $code): ?string
    {
        return str_contains($code, '.') ? substr($code, 0, strrpos($code, '.')) : null;
    }

    private function buildHierarchyDefaults(array $rows, array $matches): array
    {
        $defaults = [];
        foreach ($rows as $index => $row) {
            $defaults[$index] = ['performers' => $matches[$index]['ids'], 'lead' => $matches[$index]['ids'][0] ?? null, 'calculated_weight' => null, 'has_children' => false];
        }
        foreach (array_reverse(array_keys($rows)) as $index) {
            $code = $rows[$index]['code'];
            $childIndexes = array_filter(array_keys($rows), fn ($child) => $this->parentCode($rows[$child]['code']) === $code);
            if (!$childIndexes) continue;
            $defaults[$index]['has_children'] = true;
            foreach ($childIndexes as $child) {
                foreach ($defaults[$child]['performers'] as $performer) {
                    if (!in_array($performer, $defaults[$index]['performers'], true)) $defaults[$index]['performers'][] = $performer;
                }
            }
            $defaults[$index]['lead'] = $defaults[$index]['performers'][0] ?? null;
            if ($rows[$index]['weight'] === null) {
                $weights = array_map(fn ($child) => $rows[$child]['weight'] ?? $defaults[$child]['calculated_weight'], $childIndexes);
                if (!in_array(null, $weights, true)) $defaults[$index]['calculated_weight'] = array_sum($weights);
            }
        }
        return $defaults;
    }
}
