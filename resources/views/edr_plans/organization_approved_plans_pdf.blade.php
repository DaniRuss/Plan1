@php
    $documentBrand = app(\App\Services\DocumentBranding::class)->resolve();
@endphp
<!doctype html>
<html><head><meta charset="utf-8"><style>
@page{margin:18px}body{font-family:DejaVu Sans,sans-serif;color:#24343f;font-size:7px}h1{text-align:center;color:#123d57;margin:0 0 3px}.meta{text-align:center;color:#667780;margin-bottom:14px}.notice{padding:7px 10px;margin-bottom:10px;background:#e9f6f4;border-left:4px solid #087f77;font-size:8px}table{width:100%;border-collapse:collapse}th{background:#173b54;color:#fff;padding:4px 2px}td{border:1px solid #cfd9df;padding:3px 2px;text-align:center}td.desc{text-align:left;width:150px}.dept{background:#dff1ee;font-weight:bold;text-align:left;font-size:8px}.q{background:#e9f4f8;font-weight:bold}.footer{margin-top:10px;color:#6d7b84}
</style>@include('edr_plans.partials.branding')
</head><body>
@include('edr_plans.partials.header')

<h1>APPROVED ANNUAL PLAN ({{ $year }})</h1>
<div class="meta">{{ $plans->pluck('department_id')->unique()->count() === 1 ? $plans->first()->department->name : 'All selected departments' }} &middot; July&ndash;June &middot; EFY {{ $year }}</div>
@include('edr_plans.partials.schedule', ['schedulePlans'=>$plans])
</body></html>
