@php
    $logoPath = \App\Models\Setting::get('site_logo');
    $logoFile = $logoPath ? storage_path('app/public/' . $logoPath) : public_path('images/system-logo.png');
    $logoVersion = is_file($logoFile) ? filemtime($logoFile) : ($logoPath ? md5($logoPath) : 'system');
    $iconUrl = $logoPath ? asset('storage/' . $logoPath) : asset('images/system-logo.png');
@endphp
<link rel="icon" href="{{ $iconUrl }}?v={{ $logoVersion }}">
<link rel="apple-touch-icon" href="{{ $iconUrl }}?v={{ $logoVersion }}">
