<div class="export-brand-header">
<table><tr>
@if($documentBrand['logo_uri'])<td class="brand-logo"><img src="{{ $documentBrand['logo_uri'] }}" alt="System logo"></td>@endif
<td><div class="brand-name">{{ $documentBrand['name'] }}</div><div class="brand-label">APPROVED PLAN &amp; STRATEGIC OBJECTIVES</div></td>
<td class="brand-meta">{{ $documentBrand['abbreviation'] }}<br>Planning document<br>{{ now()->format('d M Y') }}</td>
</tr></table>
</div>
<div class="export-brand-footer">{{ $documentBrand['name'] }} &middot; Approved planning documents <span class="page-number">Page </span></div>
