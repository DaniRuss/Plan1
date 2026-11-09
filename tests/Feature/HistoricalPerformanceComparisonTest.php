<?php

namespace Tests\Feature;

use App\Models\HistoricalKpi;
use App\Models\HistoricalKpiMapping;
use App\Models\HistoricalPerformanceImport;
use App\Models\Department;
use App\Models\StrategicObjective;
use App\Services\HistoricalPerformanceWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class HistoricalPerformanceComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_models_store_missing_units_and_approved_mapping(): void
    {
        $import = HistoricalPerformanceImport::create([
            'year' => 2018,
            'original_filename' => '2018.xlsx',
            'source_hash' => str_repeat('a', 64),
            'status' => 'locked',
        ]);
        $historical = HistoricalKpi::create([
            'historical_performance_import_id' => $import->id,
            'source_row' => 207,
            'code' => '13.7',
            'original_code' => '13.7',
            'description' => 'Rail laying Machine',
            'annual_target' => 1,
            'annual_actual' => 1,
            'goal_direction' => 'increase',
            'direction_status' => 'needs_review',
            'unit_status' => 'missing',
        ]);
        $department = Department::create(['name'=>'Test','code'=>'TST','is_active'=>true,'reporting_type'=>'monthly']);
        $current = StrategicObjective::create([
            'code' => '13.7',
            'description' => 'Rail laying machine',
            'weight' => 1,
            'annual_target' => 1,
            'goal_direction' => 'increase',
            'status' => 'approved',
            'is_active' => true,
            'department_id' => $department->id,
        ]);
        $mapping = HistoricalKpiMapping::create([
            'historical_kpi_id' => $historical->id,
            'current_objective_id' => $current->id,
            'mapping_type' => 'renamed',
            'comparable' => true,
            'confidence' => 95,
            'status' => 'approved',
        ]);

        $this->assertNull($historical->fresh()->unit);
        $this->assertSame('missing', $historical->fresh()->unit_status);
        $this->assertTrue($mapping->fresh()->comparable);
        $this->assertSame('approved', $mapping->fresh()->status);
    }

    public function test_workbook_parser_applies_confirmed_code_corrections_without_inventing_a_unit(): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->fromArray([
            ['Code', 'Description', 'Weight', 'Unit', 'Annual Target', 'Annual Actual', 'Achievement', 'Performers'],
            ['11.2', 'Twentieth KPI', 1, 'No.', 10, 8, 80, 'Operation'],
            ['13.7', 'Rail laying Machine', 1, null, 1, 1, 100, 'Infrastructure'],
            ['13.7', 'Surge Arrester', 1, 'No', 2, 1, 50, 'Power'],
            ['13.7', 'Last-mile truck transportation', 1, 'Qty', 3, 3, 100, 'Logistics'],
            ['13.1', 'Truck Utilization Rate', 1, '%', 90, 80, 88.9, 'Logistics'],
            ['14.1', 'Tenth activity', 1, 'Nr.', 4, 4, 100, 'ICT'],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'hist_').'.xlsx';
        (new Xlsx($sheet))->save($path);

        $rows = app(HistoricalPerformanceWorkbook::class)->parse($path);

        $this->assertSame(['11.20', '13.7', '13.8', '13.9', '13.10', '14.10'], $rows->pluck('code')->all());
        $this->assertNull($rows->firstWhere('description', 'Rail laying Machine')['unit']);
        $this->assertSame('missing', $rows->firstWhere('description', 'Rail laying Machine')['unit_status']);
        $this->assertSame('number', $rows->firstWhere('description', 'Twentieth KPI')['normalized_unit']);
    }
}
