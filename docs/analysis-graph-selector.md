# Analysis graph selector

Applies to Performance Comparison, Dashboard Analytics and KPI Performance Board. All existing charts default to Bar graph, with Line graph, Pie chart, Histogram and Frequency polygon options. Existing filters and displayed data scope are retained. The historical annual chart shows eight priority indicators; native comparison charts show the first 12 filtered activities. All filtered rows remain available in the table and CSV.

Bar/line charts show original category values. Histograms and frequency polygons count recorded numeric values in five shared equal-width bins (one bin for constant data), retaining zeros and excluding missing data. Polygon points use numeric bin midpoints and close at zero. Pie charts for comparison/measurement data show the same frequency counts; each ring is one series. Originally categorical pie/doughnut charts retain their original composition when Pie is selected. Short help text explains each view.

Upload public/js/analysis-graphs.js and resources/views/layouts/app.blade.php to matching paths. No migrations or dependency updates. Clear generated PHP files from storage/framework/views, retaining the directory and .gitignore, then refresh the page. Script URLs use a file timestamp to refresh cached JavaScript.

Verification: JavaScript tests cover switching, immutable source data, shared bins, upper boundaries, zero/missing values, numeric strings, empty and constant datasets, polygon geometry, and mounting controls. Existing comparison/dashboard feature tests pass.

## Comparison chart readability (2026-11-04)

The two comparison chart templates opt into `data-chart-scrollable="1"`. Full activity labels wrap instead of being clipped or shortened, including after graph-type switching. Bar and line charts have a contained horizontal viewport when the available space is narrow. Users can drag with a mouse, scroll/swipe horizontally, use the left/right buttons, or focus the chart and use arrow keys/Home/End. Only the chart moves; the page does not gain horizontal overflow. Chart height accommodates multi-line labels, and regular legend clicks still work after a drag. Tooltips retain full category names; distribution modes retain their own range/coordinate semantics.

Deploy `public/js/analysis-graphs.js`, `public/css/performance-comparison.css` and both comparison templates (`_historical-comparison.blade.php`, `_comparison-workspace.blade.php`). No dependencies, database changes or cache-reset scripts are required. Existing timestamped asset URLs refresh the changed script and stylesheet.

Verified with guarded temporary SQLite browser fixtures: full source text equals all displayed tick lines, measured label edges are within the canvas, left/right buttons, keyboard navigation, mouse dragging, normal legend clicks, mode switching and mobile containment work without JavaScript errors. Frequency-polygon tooltips retain their numeric bin coordinates. All five comparison views also pass their form/navigation/pagination/CSV browser checks. All 13 JavaScript tests pass. Full PHP suite: 247 tests, 2,262 assertions, zero failures, two external-workbook tests skipped. Working database records are not modified.
