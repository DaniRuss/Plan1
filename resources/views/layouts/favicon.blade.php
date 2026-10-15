@php
    $logoPath = \App\Models\Setting::get('site_logo');
    $iconUrl = $logoPath ? asset('storage/' . $logoPath) : asset('favicon.ico');
@endphp
<link rel="icon" href="{{ $iconUrl }}?v={{ $logoPath ? md5($logoPath) : '1' }}">
<link rel="apple-touch-icon" href="{{ $iconUrl }}">
