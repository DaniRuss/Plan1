<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $year;

    public function __construct($year)
    {
        $this->year = $year;
    }

    public function collection()
    {
        return Project::with('department')
            ->where('year', $this->year)
            ->get();
    }

    public function headings(): array
    {
        return [
            'S.N',
            'Project Name',
            'Department',
            'Planned Progress',
            'Actual Performance',
            'Completion %',
            'Status',
            'Year'
        ];
    }

    public function map($project): array
    {
        static $index = 1;
        return [
            $index++,
            $project->name,
            $project->department->name,
            $project->planned_progress_desc,
            $project->performance_desc,
            $project->completion_percentage . '%',
            strtoupper(str_replace('_', ' ', $project->status)),
            $project->year
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D3D3D3']]],
        ];
    }
}
