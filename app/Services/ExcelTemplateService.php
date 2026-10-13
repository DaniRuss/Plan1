<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;

class ExcelTemplateService
{
    /**
     * Generate an Excel file by injecting data into a template.
     *
     * @param string $templateName Name of the template file in storage/app/templates
     * @param array $dataMapping Array of [cell_coordinate => value]
     * @param string $outputName Desired output filename
     * @return string Path to the generated file in local storage
     */
    public function generateReport($templateName, array $dataMapping, $outputName)
    {
        $templatePath = storage_path('app/templates/' . $templateName);

        if (!file_exists($templatePath)) {
            throw new \Exception("Template file not found: " . $templateName);
        }

        // Load the template
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Inject data based on mapping
        foreach ($dataMapping as $coordinate => $value) {
            $sheet->setCellValue($coordinate, $value);
        }

        // Save to a temporary location
        $writer = new Xlsx($spreadsheet);
        $tempPath = 'reports/' . $outputName;

        // Ensure directory exists in storage
        if (!Storage::disk('local')->exists('reports')) {
            Storage::disk('local')->makeDirectory('reports');
        }

        $absoluteTempPath = storage_path('app/' . $tempPath);
        $writer->save($absoluteTempPath);

        return $absoluteTempPath;
    }
}
