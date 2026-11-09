<?php

namespace Tests\Unit;

use App\Services\ObjectiveWorkbookImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class ObjectiveWorkbookImportTest extends TestCase
{
    public function test_reporting_frequency_column_accepts_overrides_and_inheritance(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['S.No', 'KPIs'], null, 'A1');
        $sheet->setCellValue('X1', 'Reporting Frequency');
        foreach (['INHERIT', 'MONTHLY', 'weekly', 'DAILY', ''] as $index => $frequency) {
            $row = $index + 2;
            $sheet->setCellValue('A'.$row, (string) $row)->setCellValue('B'.$row, 'Cargo');
            $sheet->setCellValue('X'.$row, $frequency);
        }
        $path = tempnam(sys_get_temp_dir(), 'frequency-').'.xlsx';
        try {
            (new Xlsx($book))->save($path);
            $rows = (new ObjectiveWorkbookImport())->parse($path);
            $this->assertSame([null, 'monthly', 'weekly', 'daily', null], array_column($rows, 'reporting_frequency'));
            $sheet->setCellValue('X2', 'YEARLY');
            (new Xlsx($book))->save($path);
            $this->expectException(\InvalidArgumentException::class);
            (new ObjectiveWorkbookImport())->parse($path);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_formula_targets_preserve_pax_units_and_underlying_precision(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(['S.No','KPIs'], null, 'A1');
        $sheet->setCellValue('A2','3.1')->setCellValue('B2','Transport Inter-City passengers');
        $sheet->setCellValue('D2','PAX in 1,000')->setCellValue('F2',510.5);
        $sheet->setCellValue('J2','=F2/4')->setCellValue('G2','=J2/3');
        $sheet->setCellValue('H2','=0')->setCellValue('I2','=SUM(G2:H2)');
        $sheet->getStyle('G2')->getNumberFormat()->setFormatCode('0');
        $path = tempnam(sys_get_temp_dir(),'pax-');
        try {
            (new Xlsx($book))->save($path);
            $row = (new ObjectiveWorkbookImport())->parse($path)[0];
            $this->assertSame('PAX in 1,000',$row['unit']);
            $this->assertEqualsWithDelta(42.5416666667,$row['july_target'],0.00000001);
            $this->assertSame(127.625,$row['q1_target']);
            $this->assertSame(0.0,$row['august_target']);
            $this->assertEqualsWithDelta($row['july_target'],$row['september_target'],0.00000001);
            $this->assertNull($row['october_target']);
            $sheet->setCellValue('G2','=1/0');
            (new Xlsx($book))->save($path);
            $this->expectException(\InvalidArgumentException::class);
            (new ObjectiveWorkbookImport())->parse($path);
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_provided_action_plan_can_be_previewed_without_writing(): void
    {
        $path = 'E:/EDR/Plan and report project/2019 EFY internal Action plan Distributed to Departments (1).xlsx';
        if (!is_file($path)) $this->markTestSkipped('The reference workbook is not available on this computer.');
        $rows = (new ObjectiveWorkbookImport())->parse($path);
        $this->assertGreaterThan(200, count($rows));
        $this->assertSame('1', $rows[0]['code']);
    }

    public function test_it_preserves_blank_and_zero_targets_and_converts_excel_time_to_hours(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A1', 'S.No');
        $sheet->setCellValue('B1', 'KPIs');
        $sheet->setCellValue('A2', '2.2');
        $sheet->setCellValue('B2', 'Turnaround time');
        $sheet->setCellValue('C2', 0.4);
        $sheet->setCellValue('D2', 'Hrs.');
        $sheet->setCellValue('F2', 3);
        $sheet->setCellValue('G2', 0);
        $sheet->setCellValue('H2', 0.5);
        $sheet->getStyle('H2')->getNumberFormat()->setFormatCode('h:mm');
        $path = tempnam(sys_get_temp_dir(), 'objective-').'.xlsx';
        (new Xlsx($book))->save($path);
        try {
            $rows = (new ObjectiveWorkbookImport())->parse($path);
            $this->assertCount(1, $rows);
            $this->assertSame(0.0, $rows[0]['july_target']);
            $this->assertSame(12.0, $rows[0]['august_target']);
            $this->assertNull($rows[0]['september_target']);
        } finally {
            unlink($path);
        }
    }
}

