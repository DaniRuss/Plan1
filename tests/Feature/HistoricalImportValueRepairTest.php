<?php

namespace Tests\Feature;

use App\Models\{AuditLog, HistoricalKpi, HistoricalKpiMapping, HistoricalPerformanceImport, Role, User};
use App\Services\{HistoricalPerformanceImporter, HistoricalPerformanceWorkbook};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class HistoricalImportValueRepairTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryWorkbooks = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryWorkbooks as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }

    private function workbook(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['Code','Description','Weight','Unit','Annual Target','Annual Actual','Achievement','Performers'],
            ['2.2','Container turnaround duration',1,'Hrs',3,3.5875,83.6,'Operation'],
            ['2.3','Plain hour counts',1,'Hours',72,86.1,83.6,'Operation'],
            ['2.4','Text elapsed hours',1,'Hrs','72:00:00','86:06:00',83.6,'Operation'],
            ['2.5','Time in days',1,'Days',3,3.5875,83.6,'Operation'],
            ['2.6','Money with a currency format',1,'Birr',1200.125,0,0,'Finance'],
            ['2.7','Excel percentage cells',1,'%',.9,.75,.833333,'Finance'],
            ['2.8','Formula duration',1,'Hrs','=72/24','=86.1/24',83.6,'Operation'],
            ['2.9','Missing result',1,'Number',null,null,null,'Operation'],
            ['2.10','Rounded display retains stored precision',1,'No.',1.234567,1.198765,97.1,'Operation'],
        ], null, 'A1', true);
        $sheet->setCellValueExplicit('A10', '2.10', DataType::TYPE_STRING);
        foreach (['E2:F2','E5:F5','E8:F8'] as $range) $sheet->getStyle($range)->getNumberFormat()->setFormatCode('[h]:mm:ss');
        $sheet->getStyle('E6:F6')->getNumberFormat()->setFormatCode('"ETB" #,##0.00');
        $sheet->getStyle('E7:G7')->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('E10:F10')->getNumberFormat()->setFormatCode('0.0');
        $path = tempnam(sys_get_temp_dir(), 'historical_duration_');
        $this->temporaryWorkbooks[] = $path;
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_import_reads_elapsed_durations_without_losing_days_minutes_or_seconds(): void
    {
        $rows = app(HistoricalPerformanceWorkbook::class)->parse($this->workbook())->keyBy('code');
        foreach (['2.2','2.3','2.4','2.8'] as $code) {
            $this->assertSame(72.0, $rows[$code]['annual_target'], $code);
            $this->assertEqualsWithDelta(86.1, $rows[$code]['annual_actual'], .000001, $code);
        }
        $this->assertSame(3.0, $rows['2.5']['annual_target']);
        $this->assertEqualsWithDelta(3.5875, $rows['2.5']['annual_actual'], .000001);
    }

    public function test_formatted_numbers_preserve_precision_percentage_scale_zero_and_blanks(): void
    {
        $rows = app(HistoricalPerformanceWorkbook::class)->parse($this->workbook())->keyBy('code');
        $this->assertSame(1200.125, $rows['2.6']['annual_target']);
        $this->assertSame(0.0, $rows['2.6']['annual_actual']);
        $this->assertSame(90.0, $rows['2.7']['annual_target']);
        $this->assertSame(75.0, $rows['2.7']['annual_actual']);
        $this->assertEqualsWithDelta(83.3333, $rows['2.7']['reported_achievement'], .000001);
        $this->assertSame(1.234567, $rows['2.10']['annual_target']);
        $this->assertNull($rows['2.9']['annual_target']);
        $this->assertNull($rows['2.9']['annual_actual']);
    }

    public function test_reupload_repairs_only_missing_values_in_the_identical_existing_source(): void
    {
        $path = $this->workbook();
        $user = User::factory()->create();
        $import = HistoricalPerformanceImport::create(['year'=>2018,'original_filename'=>'Original.xlsx','source_hash'=>hash_file('sha256',$path),'status'=>'locked']);
        $row = $import->kpis()->create(['source_row'=>2,'code'=>'2.2','description'=>'Container turnaround duration','unit'=>'Hrs','weight'=>1,'annual_target'=>null,'annual_actual'=>null,'reported_achievement'=>83.6,'kpi_code'=>'K7','goal_direction'=>'decrease','direction_status'=>'confirmed']);
        HistoricalKpiMapping::create(['historical_kpi_id'=>$row->id,'mapping_type'=>'retired','status'=>'approved','comparable'=>false]);
        $existing = $import->kpis()->create(['source_row'=>3,'code'=>'2.3','description'=>'Plain hour counts','unit'=>'Hours','weight'=>1,'annual_target'=>0,'annual_actual'=>90,'reported_achievement'=>83.6]);
        $importer = app(HistoricalPerformanceImporter::class);
        $result = $importer->import(new UploadedFile($path,'Original.xlsx',null,null,true),2018,$user);
        $this->assertSame($import->id, $result->id);
        $this->assertSame(1, HistoricalPerformanceImport::count());
        $this->assertSame(2, HistoricalKpi::count());
        $this->assertSame('72.0000', $row->fresh()->annual_target);
        $this->assertSame('86.1000', $row->fresh()->annual_actual);
        $this->assertSame('83.6000', $row->fresh()->reported_achievement);
        $this->assertSame('K7', $row->fresh()->kpi_code);
        $this->assertSame('confirmed', $row->fresh()->direction_status);
        $this->assertSame('approved', $row->mappings()->sole()->status);
        $this->assertSame('0.0000', $existing->fresh()->annual_target);
        $this->assertSame('90.0000', $existing->fresh()->annual_actual);
        $this->assertSame(2, $importer->repairSummary['values']);
        $this->assertSame(1, AuditLog::where('action','historical_import_values_repaired')->count());
        $importer->import(new UploadedFile($path,'Original.xlsx',null,null,true),2018,$user);
        $this->assertSame(0, $importer->repairSummary['values']);
        $this->assertSame(1, AuditLog::where('action','historical_import_values_repaired')->count());
    }

    public function test_reupload_skips_rows_whose_identity_or_unit_was_changed(): void
    {
        $path = $this->workbook();
        $import = HistoricalPerformanceImport::create(['year'=>2018,'original_filename'=>'Original.xlsx','source_hash'=>hash_file('sha256',$path),'status'=>'locked']);
        $row = $import->kpis()->create(['source_row'=>2,'code'=>'2.2','description'=>'Container turnaround duration','unit'=>'Days']);
        $importer = app(HistoricalPerformanceImporter::class);
        $importer->import(new UploadedFile($path,'Original.xlsx',null,null,true),2018,User::factory()->create());
        $this->assertNull($row->fresh()->annual_target);
        $this->assertNull($row->fresh()->annual_actual);
        $this->assertTrue(property_exists($importer, 'repairSummary'));
        $this->assertSame(1, $importer->repairSummary['skipped']);
    }

    public function test_duration_units_and_literal_percent_signs_are_not_guessed(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['Code','Description','Weight','Unit','Annual Target','Annual Actual','Achievement','Performers'],
            ['3.1','Elapsed whole hours',1,'Hrs',3,0,0,'Operation'],
            ['3.2','Elapsed minutes',1,'Minutes',.5,.25,50,'Operation'],
            ['3.3','Elapsed seconds',1,'Seconds',.5,.25,50,'Operation'],
            ['3.4','Literal percent suffix',1,'%',90,75,83.3,'Operation'],
            ['3.5','Unspecified duration unit',1,null,3,3.5875,83.6,'Operation'],
            ['3.6','Calendar dates are not durations',1,'Hrs',45000,45001,null,'Operation'],
            [null,'Uncoded explicit zero',1,'Number',0,0,0,'Operation'],
        ], null, 'A1', true);
        $sheet->getStyle('E2:F2')->getNumberFormat()->setFormatCode('[h]');
        $sheet->getStyle('E3:F3')->getNumberFormat()->setFormatCode('[m]');
        $sheet->getStyle('E4:F4')->getNumberFormat()->setFormatCode('[s]');
        $sheet->getStyle('E5')->getNumberFormat()->setFormatCode('0"%"');
        $sheet->getStyle('F5')->getNumberFormat()->setFormatCode('0\\%');
        $sheet->getStyle('E6:F6')->getNumberFormat()->setFormatCode('[h]:mm:ss');
        $sheet->getStyle('E7:F7')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $path = tempnam(sys_get_temp_dir(), 'historical_duration_');
        $this->temporaryWorkbooks[] = $path;
        (new Xlsx($book))->save($path);
        $rows = app(HistoricalPerformanceWorkbook::class)->parse($path)->keyBy('source_row');
        $this->assertSame(72.0, $rows[2]['annual_target']);
        $this->assertSame(0.0, $rows[2]['annual_actual']);
        $this->assertSame(720.0, $rows[3]['annual_target']);
        $this->assertSame(43200.0, $rows[4]['annual_target']);
        $this->assertSame(90.0, $rows[5]['annual_target']);
        $this->assertSame(75.0, $rows[5]['annual_actual']);
        $this->assertNull($rows[6]['annual_target']);
        $this->assertNull($rows[7]['annual_target']);
        $this->assertSame(0.0, $rows[8]['annual_target']);
    }

    public function test_existing_import_repair_does_not_create_or_reset_mapping_suggestions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['must_change_password'=>false]);
        $user->roles()->attach(Role::where('name','system_admin')->firstOrFail());
        $path = $this->workbook();
        $import = HistoricalPerformanceImport::create(['year'=>2018,'original_filename'=>'Original.xlsx','source_hash'=>hash_file('sha256',$path),'status'=>'locked']);
        $row = $import->kpis()->create(['source_row'=>2,'code'=>'2.2','description'=>'Container turnaround duration','unit'=>'Hrs']);
        $this->actingAs($user)->from('/performance-comparison')->post(route('performance.historical.import'), ['historical_year'=>2018,'repair_only'=>1,'workbook'=>new UploadedFile($path,'Original.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true)])
            ->assertRedirect('/performance-comparison')->assertSessionHas('success', fn ($text)=>str_contains($text,'restored'));
        $this->assertSame('72.0000', $row->fresh()->annual_target);
        $this->assertSame(0, HistoricalKpiMapping::count());
    }

    public function test_only_the_applicable_number_format_section_controls_scaling(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['Code','Description','Weight','Unit','Annual Target','Annual Actual','Achievement','Performers'],
            ['4.1','Positive numeric section',1,'%',.9,-.9,null,'Finance'],
            ['4.2','Positive plain number',1,'Hrs',3,-3,null,'Operation'],
            ['4.3','Positive duration',1,'Hrs',3,null,null,'Operation'],
            ['4.4','Month-only date',1,'Hrs',45000,null,null,'Operation'],
            ['4.5','Conditional scale',1,'%',.9,2,null,'Finance'],
            ['4.6','Literal semicolon and percent',1,'%',.9,null,null,'Finance'],
        ], null, 'A1', true);
        foreach (['E2:F2'=>'0.0;0.0%;0', 'E3:F3'=>'0.00;[h]:mm:ss', 'E4'=>'[h]:mm:ss;yyyy-mm-dd', 'E5'=>'mm', 'E6:F6'=>'[>=1]0.0;[<1]0.0%;0', 'E7'=>'0.0";%";0.0%'] as $range=>$format) {
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode($format);
        }
        $path = tempnam(sys_get_temp_dir(), 'historical_duration_');
        $this->temporaryWorkbooks[] = $path;
        (new Xlsx($book))->save($path);
        $rows = app(HistoricalPerformanceWorkbook::class)->parse($path)->keyBy('source_row');
        $this->assertSame(.9, $rows[2]['annual_target']);
        $this->assertSame(-90.0, $rows[2]['annual_actual']);
        $this->assertSame(3.0, $rows[3]['annual_target']);
        $this->assertSame(-72.0, $rows[3]['annual_actual']);
        $this->assertSame(72.0, $rows[4]['annual_target']);
        $this->assertNull($rows[5]['annual_target']);
        $this->assertSame(90.0, $rows[6]['annual_target']);
        $this->assertSame(2.0, $rows[6]['annual_actual']);
        $this->assertSame(.9, $rows[7]['annual_target']);
    }

    public function test_repair_does_not_equate_unknown_units_with_different_punctuation(): void
    {
        $path = $this->workbook();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $book->getActiveSheet()->setCellValue('D3', 'kg/m');
        (new Xlsx($book))->save($path);
        $import = HistoricalPerformanceImport::create(['year'=>2018,'original_filename'=>'Original.xlsx','source_hash'=>hash_file('sha256',$path),'status'=>'locked']);
        $row = $import->kpis()->create(['source_row'=>3,'code'=>'2.3','description'=>'Plain hour counts','unit'=>'kg*m']);
        $importer = app(HistoricalPerformanceImporter::class);
        $importer->import(new UploadedFile($path,'Original.xlsx',null,null,true),2018,User::factory()->create());
        $this->assertNull($row->fresh()->annual_target);
        $this->assertSame(1, $importer->repairSummary['skipped']);
    }

    public function test_repair_only_rejects_a_non_matching_workbook_without_creating_a_source(): void
    {
        $path = $this->workbook();
        $user = User::factory()->create();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(HistoricalPerformanceImporter::class)->import(new UploadedFile($path,'Original.xlsx',null,null,true),2018,$user,true);
        } finally {
            $this->assertSame(0, HistoricalPerformanceImport::count());
            $this->assertSame(0, HistoricalKpi::count());
        }
    }

    public function test_browser_repair_rejects_a_non_matching_source_with_a_clear_error(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['must_change_password'=>false]);
        $user->roles()->attach(Role::where('name','system_admin')->firstOrFail());
        $this->actingAs($user)->from('/performance-comparison')->post(route('performance.historical.import'),[
            'historical_year'=>2018,'repair_only'=>1,
            'workbook'=>new UploadedFile($this->workbook(),'Original.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true),
        ])->assertRedirect('/performance-comparison')->assertSessionHasErrors('workbook');
        $this->assertSame(0,HistoricalPerformanceImport::count());
        $this->assertSame(0,HistoricalKpi::count());
    }
}
