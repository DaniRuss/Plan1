<style>
@page { margin: 106px 24px 42px; }
body { color: #263d35; }
h1 { color: #{{ $documentBrand['dark'] }}; font-size: 18px; letter-spacing: .5px; margin: 4px 0 6px; }
h2 { color: #{{ $documentBrand['accent'] }}; border-bottom: 2px solid #{{ $documentBrand['accent'] }}; padding-bottom: 6px; }
th { background: #{{ $documentBrand['accent'] }}; color: #fff; }
td { border-color: #d6e2dd; }
.dept, .root { background: #{{ $documentBrand['tint'] }}; color: #{{ $documentBrand['dark'] }}; }
.q { background: #{{ $documentBrand['pale'] }}; color: #{{ $documentBrand['dark'] }}; }
.notice { background: #{{ $documentBrand['pale'] }}; border-left-color: #{{ $documentBrand['accent'] }}; }
.meta { color: #61786e; margin-bottom: 16px; }
.implementers { text-align: left; min-width: 85px; }
.quarter-heading { border-left: 2px solid #fff; }
thead { display: table-header-group; }
.export-brand-header { position: fixed; top: -88px; left: 0; right: 0; height: 68px; border-bottom: 3px solid #{{ $documentBrand['accent'] }}; }
.export-brand-header table { width: 100%; font-size: 10px; }
.export-brand-header td { border: 0; padding: 0 0 8px; vertical-align: middle; text-align: left; }
.export-brand-header .brand-logo { width: 150px; }
.export-brand-header img { max-width: 135px; max-height: 50px; }
.export-brand-header .brand-name { color: #{{ $documentBrand['dark'] }}; font-size: 15px; font-weight: bold; }
.export-brand-header .brand-label { color: #{{ $documentBrand['accent'] }}; font-size: 8px; margin-top: 5px; letter-spacing: 1px; }
.export-brand-header .brand-meta { text-align: right; color: #61786e; font-size: 8px; }
.export-brand-footer { position: fixed; bottom: -28px; left: 0; right: 0; border-top: 1px solid #{{ $documentBrand['accent'] }}; padding-top: 7px; font-size: 7px; color: #61786e; }
.export-brand-footer .page-number { float: right; }
.export-brand-footer .page-number:after { content: counter(page); }
</style>
