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
        th, td { border: 1px solid #ccc; padding: 3px 2px; text-align: center; white-space: normal; word-wrap: break-word; vertical-align: middle; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
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
        <colgroup>
            <col style="width:3%"><col style="width:18%"><col style="width:3%"><col style="width:5%"><col style="width:4%"><col style="width:5%">
            @php($periodColumnCount = collect($periodGroups)->sum(fn ($group) => count($group['months']) + 1))
            @foreach($periodGroups as $group)
                @foreach($group['months'] as $m)<col style="width:{{ 52 / $periodColumnCount }}%">@endforeach
                <col style="width:{{ 52 / $periodColumnCount }}%">
            @endforeach
            <col style="width:10%">
        </colgroup>
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
                    <td>{{ \App\Support\NumberFormatter::format($row['weight']) }}</td>
                    <td class="small">{{ $row['unit'] }}</td>
                    <td>{{ \App\Support\NumberFormatter::format($row['baseline']) }}</td>
                    <td>{{ is_null($row['annual_target']) ? 'N/A' : \App\Support\NumberFormatter::format($row['annual_target'],3) }}</td>
                    
                    @foreach($periodGroups as $group)
                        @foreach($group['months'] as $m)
                            @php($cell = $row['months'][$m])
                            <td class="text-primary">
                                @include('reports._master_actual', ['pdf' => true])
                            </td>
                        @endforeach
                        @php($q = $group['quarter'])
                        @php($cell = $row['quarters'][$q])
                        <td class="text-info" style="background:#e3f2fd;">
                            @include('reports._master_actual', ['pdf' => true])
                        </td>
                    @endforeach

                    <td class="small">{{ $row['performers'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
