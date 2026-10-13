<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Master Performance Report - {{ $deptName }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 2px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-left { text-align: left; padding-left: 5px; }
        .bg-light { background-color: #f9f9f9; }
        .root-row { background-color: #e9ecef; font-weight: bold; }
        .child-row { background-color: #f8f9fa; font-weight: bold; }
        .text-primary { color: #007bff; }
        .text-info { color: #17a2b8; }
        .small { font-size: 7px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>MASTER PERFORMANCE REPORT ({{ $year }})</h2>
        <p>{{ $deptName }} - Overall Strategic Matrix</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30">S.No</th>
                <th width="120">Objective / Activity</th>
                <th width="25">Wt.</th>
                <th width="25">Ut.</th>
                <th width="35">Base</th>
                <th width="35">Ann.</th>
                @foreach($periodGroups as $group)
                    @foreach($group['months'] as $m)
                        <th width="25">{{ strtoupper(substr($m, 0, 1)) }}</th>
                    @endforeach
                    <th width="30" style="background:#d1ecf1;">{{ strtoupper($group['quarter']) }}</th>
                @endforeach
                <th width="60">Performer</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gridData as $row)
                <tr class="{{ $row['is_root'] ? 'root-row' : ($row['is_child'] ? 'child-row' : '') }}">
                    <td>{{ $row['code'] }}</td>
                    <td class="text-left">{{ $row['description'] }}</td>
                    <td>{{ $row['weight'] ?: '-' }}</td>
                    <td class="small">{{ $row['unit'] }}</td>
                    <td>{{ $row['baseline'] }}</td>
                    <td>{{ is_null($row['annual_target']) ? 'N/A' : number_format($row['annual_target'], 1) }}</td>
                    
                    @foreach($periodGroups as $group)
                        @foreach($group['months'] as $m)
                            @php($cell = $row['months'][$m])
                            <td class="text-primary">
                                @if(!$cell['planned']) N/A
                                @elseif(!$cell['reported']) Not Reported
                                @else {{ $cell['is_rollup'] ? 'Roll-up' : number_format((float)$cell['actual'], 2) }}<br>{{ number_format($cell['scorecard_achievement'], 1) }}% ({{ $cell['status'] }})@if($cell['raw_achievement'] > 100)<br>Raw {{ number_format($cell['raw_achievement'], 1) }}%@endif @if(!$cell['is_rollup'] && abs((float)$cell['effective_weight'] - (float)$row['weight']) > 0.0001)<br>Eff. wt {{ number_format((float)$cell['effective_weight'], 4) }}@endif
                                @endif
                            </td>
                        @endforeach
                        @php($q = $group['quarter'])
                        @php($cell = $row['quarters'][$q])
                        <td class="text-info" style="background:#e3f2fd;">
                            @if(!$cell['planned']) N/A
                            @elseif(!$cell['reported']) Not Reported
                            @else {{ $cell['is_rollup'] ? 'Roll-up' : number_format((float)$cell['actual'], 2) }}<br>{{ number_format($cell['scorecard_achievement'], 1) }}% ({{ $cell['status'] }})@if($cell['raw_achievement'] > 100)<br>Raw {{ number_format($cell['raw_achievement'], 1) }}%@endif @if(!$cell['is_rollup'] && abs((float)$cell['effective_weight'] - (float)$row['weight']) > 0.0001)<br>Eff. wt {{ number_format((float)$cell['effective_weight'], 4) }}@endif
                            @endif
                        </td>
                    @endforeach

                    <td class="small">{{ $row['performers'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
