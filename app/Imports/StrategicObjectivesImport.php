<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\StrategicObjective;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class StrategicObjectivesImport implements OnEachRow, WithHeadingRow
{
    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row = $row->toArray();

        // Keys based on debug output:
        // "sno" => "4"

        $code = $row['sno'] ?? $row['s_no'] ?? $row['code'] ?? null;
        if (!$code) {
            return;
        }
        $code = rtrim(trim(str_replace("\xc2\xa0", '', (string) $code)), '.');

        $kpiCode = $row['kpi_code'] ?? $row['kpi'] ?? null;

        // Parse Description
        $description = $row['strategic_objectives_and_activities'] ?? $row['strategic_objectives'] ?? $row['kpis'] ?? '';

        // Parse Weight
        $weight = $row['weight'] ?? 0;

        // Unit
        $unit = $row['unit'] ?? null;

        // Baseline - Dynamic Search
        $baselineValue = null;
        $baselineYear = 'Existing';

        // Find any key starting with 'baseline'
        foreach (array_keys($row) as $key) {
            if (str_starts_with($key, 'baseline')) {
                $baselineValue = $row[$key];
                // Optional: Extract year from key if needed, e.g. baseline_2018
                break;
            }
        }

        // Annual Target
        $annualTarget = $row['annual_target'] ?? null;
        $targetYear = $row['target_year'] ?? $row['year'] ?? null;

        // Performers
        $performerStr = $row['performer'] ?? '';
        $performers = [];
        $departmentId = null;

        if ($performerStr) {
            // Split by comma or &
            $names = preg_split('/[&,]/', $performerStr);
            foreach ($names as $name) {
                $name = trim($name);
                // Try to find department
                // Fuzzy match might be needed, but let's try exact first + mapped aliases
                $dept = Department::where('name', $name)
                    ->orWhere('code', $name)
                    ->first();

                if ($dept) {
                    $performers[] = $dept->id;
                    if (!$departmentId) {
                        $departmentId = $dept->id; // First one is owner
                    }
                }
            }
        }

        // If no department found, maybe set to a default or leave null (but it's required in DB).
        // For now, if null, we might skip or fail? 
        // Migration says constrained()->cascadeOnDelete(). It implies it must exist.
        // We will fallback to a default department if needed or create one? No, valid departments exist.
        // If no match, we temporarily assign to the first department (ID 1) or look for "Planning" dept.
        if (!$departmentId) {
            $defaultDept = Department::first();
            $departmentId = $defaultDept ? $defaultDept->id : 1;
        }

        // Logic to find Parent
        // Code: "14" -> Parent is null
        // Code: "14.1" -> Parent is "14"
        // Code: "14.1.2" -> Parent is "14.1"
        $parentId = null;
        if (str_contains($code, '.')) {
            $parts = explode('.', $code);
            array_pop($parts); // Remove last part
            $parentCode = implode('.', $parts);

            $parent = StrategicObjective::where('code', $parentCode)->first();
            if ($parent) {
                $parentId = $parent->id;
            }
        }

        // Fidelity Logic: Template and Summary types from sheet
        $templateType = $row['template_type'] ?? 'standard';
        $summaryType = $row['summary_type'] ?? null;
        $dataSource = $row['data_source'] ?? null;

        $targets = [];
        foreach (['july', 'aug', 'sept', 'oct', 'nov', 'dec', 'jan', 'feb', 'march', 'apr', 'may', 'june'] as $heading) {
            $modelMonth = ['aug' => 'august', 'sept' => 'september', 'oct' => 'october', 'nov' => 'november',
                'dec' => 'december', 'jan' => 'january', 'feb' => 'february', 'apr' => 'april'][$heading] ?? $heading;
            $targets[$modelMonth . '_target'] = is_numeric($row[$heading] ?? null) ? $row[$heading] : null;
        }
        foreach (['q1', 'q2', 'q3', 'q4'] as $quarter) {
            $targets[$quarter . '_target'] = is_numeric($row[$quarter] ?? null) ? $row[$quarter] : null;
        }

        // Flow KPIs have annual/quarter totals that equal period sums. Snapshot KPIs
        // (rates, speed, availability, project progress) use end-period checkpoints.
        $numericMonths = array_filter(array_intersect_key($targets, array_flip(['july_target','august_target','september_target'])), 'is_numeric');
        $q1 = $targets['q1_target'] ?? null;
        $targetDistribution = $q1 !== null && count($numericMonths) === 3
            && abs(array_sum($numericMonths) - $q1) < 0.01 ? 'sum' : 'checkpoint';

        // Auto-detect summary type for parents if not provided
        if (!$summaryType && $parentId === null) {
            $summaryType = 'weighted'; // Default for top-level
        }

        // Fidelity Logic: Reference-Only detection
        $isReferenceOnly = str_starts_with(trim($description), '*') || stripos($description, 'total') !== false;

        // Fidelity Logic: Calculated Weight detection
        // If weight is null/0 and it looks like a parent (handled by $parentId logic below basically)
        // or explicitly explicitly marked 'CALC' in weight column
        $isCalculatedWeight = (strtolower($row['weight'] ?? '') === 'calc');
        if (!$isCalculatedWeight && $weight == 0 && !str_contains($code, '.')) {
            // Top level objectives with 0 weight are often containers
            $isCalculatedWeight = true;
        }
        if (!str_contains($code, '.') && str_starts_with(strtolower(trim($description)), 'objective ')) {
            $isCalculatedWeight = true;
        }

        StrategicObjective::updateOrCreate(
            ['code' => $code],
            [
                'kpi_code' => $kpiCode,
                'description' => $description,
                'weight' => is_numeric($weight) ? $weight : 0,
                'unit' => $unit,
                'baseline_value' => $baselineValue,
                'baseline_year' => $baselineYear,
                'annual_target' => $annualTarget,
                'target_distribution' => $targetDistribution,
                'target_year' => $targetYear,
                'department_id' => $departmentId,
                'parent_id' => $parentId,
                'performers' => $performers,
                'is_active' => true,
                'is_reference_only' => $isReferenceOnly,
                'is_calculated_weight' => $isCalculatedWeight,
                'template_type' => $templateType,
                'summary_type' => $summaryType,
                'data_source' => $dataSource,
                ...$targets,
            ]
        );
    }
}
