<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeTaskExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $tasks;
    protected $employee;
    protected $year;

    public function __construct($tasks, $employee, $year)
    {
        $this->tasks = $tasks;
        $this->employee = $employee;
        $this->year = $year;
    }

    public function collection()
    {
        return $this->tasks;
    }

    public function headings(): array
    {
        return [
            ['Performance Report - ' . $this->employee->first_name . ' ' . $this->employee->last_name],
            ['Year: ' . $this->year],
            [''],
            [
                'Task Title',
                'Status',
                'Completion %',
                'Strategic Objective',
                'Assigned Date',
                'Due Date',
                'Narrative/Result',
            ]
        ];
    }

    public function map($task): array
    {
        $report = $task->reports->where('status', 'approved')->first();

        return [
            $task->title,
            ucfirst($task->status),
            \App\Support\NumberFormatter::format($task->completion_percentage) . '%',
            $task->planItem->strategicObjective->description ?? 'General',
            $task->created_at->format('Y-m-d'),
            $task->due_date ?? '-',
            $report ? $report->narrative : 'No approved report yet',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
