<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Performance Report - {{ $employee->first_name }} {{ $employee->last_name }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #444;
            padding-bottom: 15px;
        }

        .logo {
            height: 50px;
            margin-bottom: 8px;
        }

        h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }

        h2 {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            background: #f0f0f0;
            padding: 5px 10px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px 6px;
            text-align: left;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .bg-success {
            background-color: #d4edda;
            color: #155724;
        }

        .bg-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .bg-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .footer {
            margin-top: 30px;
            font-size: 9px;
            text-align: center;
            color: #777;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Individual Performance Report</h1>
        <h2>{{ $employee->first_name }} {{ $employee->last_name }}</h2>
        <p>{{ $employee->job_title }} | {{ $employee->department->name ?? 'N/A' }} | Year: {{ $year }}</p>
    </div>

    <div class="section">
        <div class="section-title">Performance Summary</div>
        <table style="width: 50%;">
            <tr>
                <th>Total Tasks Assigned</th>
                <td class="text-center">{{ $tasks->count() }}</td>
            </tr>
            <tr>
                <th>Average Progress</th>
                <td class="text-center">{{ round($tasks->avg('completion_percentage'), 1) }}%</td>
            </tr>
            <tr>
                <th>Tasks Completed</th>
                <td class="text-center">{{ $tasks->where('status', 'completed')->count() }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Detailed Task Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 40%">Task</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Progress</th>
                    <th class="text-center">Due Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    <tr>
                        <td>
                            <strong>{{ $task->title }}</strong><br>
                            <span style="font-size: 8px; color: #666;">Obj:
                                {{ $task->planItem->strategicObjective->description ?? 'General' }}</span>
                        </td>
                        <td class="text-center">
                            <span
                                class="badge {{ $task->status == 'completed' ? 'bg-success' : ($task->status == 'pending' ? 'bg-warning' : 'bg-info') }}">
                                {{ $task->status }}
                            </span>
                        </td>
                        <td class="text-center">{{ \App\Support\NumberFormatter::format($task->completion_percentage) }}%</td>
                        <td class="text-center">{{ $task->due_date ? date('Y-m-d', strtotime($task->due_date)) : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Supervisor Comments & Narrative</div>
        @foreach($tasks as $task)
            @php $report = $task->reports->where('status', 'approved')->first(); @endphp
            @if($report)
                <div style="margin-bottom: 10px; border-bottom: 1px dotted #ccc; padding-bottom: 5px;">
                    <strong>{{ $task->title }}:</strong>
                    <p style="margin: 5px 0;">{{ $report->narrative }}</p>
                    @if($report->review_comments)
                        <p style="margin: 0; font-size: 9px; color: #28a745;"><i>Review: {{ $report->review_comments }}</i></p>
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    <div class="footer">
        Generated by IPRPMS on {{ date('Y-m-d H:i') }}
    </div>
</body>

</html>