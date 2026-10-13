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

