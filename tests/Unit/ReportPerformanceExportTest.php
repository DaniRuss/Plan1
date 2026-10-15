<?php

namespace Tests\Unit;

use App\Exports\ReportPerformanceExport;
use App\Services\PerformanceLevel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportPerformanceExportTest extends TestCase
{
    public function test_reference_format_export_can_be_generated(): void
    {
        $level = app(PerformanceLevel::class)->for(88);
        $row = [
            '_item_id' => 1,
            '_parent_item_id' => null,
            'code' => '1.1',
            'hierarchy_level' => 2,
            'objective' => 'Sample performed KPI',
            'is_planned' => true,
            'weight' => 10,
            'base_weight' => 10,
            'measurement' => 'Number',
            'quarter_plan' => 100,
            'quarter_perf' => 88,
            'quarter_comp' => 88,
            'raw_achievement' => 125,
            'scorecard_achievement' => 100,
            'performance_status' => 'Overachieved',
            'performer' => 'Operations',
            'score_factor' => 0.88,
            'weighted_score' => 8.8,
            'score_weight' => 10,
            'has_children' => false,
            'performance_level' => $level,
        ];

        $binary = Excel::raw(new ReportPerformanceExport([$row], 'JULY 2019'), ExcelFormat::XLSX);

        $this->assertStringStartsWith('PK', $binary);
        $this->assertGreaterThan(1000, strlen($binary));

        $html = view('reports._performance_table', [
            'performanceData' => [$row],
            'period' => 'month:july',
        ])->render();
        $this->assertStringContainsString('TOTAL EDR ACHIEVEMENT', $html);
        $this->assertStringContainsString('#FFC000', $html);
        $this->assertStringContainsString('125%', $html);
        $this->assertStringContainsString('Overachieved', $html);

        $row['is_planned'] = false;
        $row['performance_level'] = app(PerformanceLevel::class)->for(null);
        $unplannedHtml = view('reports._performance_table', [
            'performanceData' => [$row],
            'period' => 'month:july',
        ])->render();
        $this->assertStringContainsString('N/A', $unplannedHtml);
        $this->assertStringContainsString('#6C757D', $unplannedHtml);
    }
}
