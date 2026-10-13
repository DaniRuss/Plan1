<?php
$file = 'c:\wamp64\www\iprpms\app\Http\Controllers\EdrReportController.php';
$content = file_get_contents($file);

$search1 = "        \$year   = (int) \$request->get('year', now()->year);";
$replace1 = "        \$defaultYear = \App\Models\Plan::where('status', 'approved')->max('year') ?? now()->year;\n        \$year   = (int) \$request->get('year', \$defaultYear);";

if (strpos($content, $search1) !== false) {
    // Replace it in kpiDashboard specifically
    $start = strpos($content, 'public function kpiDashboard');
    if ($start !== false) {
        $part1 = substr($content, 0, $start);
        $part2 = substr($content, $start);
        $part2 = str_replace($search1, $replace1, $part2);
        file_put_contents($file, $part1 . $part2);
        echo "Successfully updated kpiDashboard year.\n";
    }
} else {
    echo "Could not find target string in kpiDashboard.\n";
    // Let's do a regex replace to be safe against different whitespace
    $content = preg_replace('/function kpiDashboard\(Request \$request\)\s*\{\s*\$year   = \(int\) \$request->get\(\'year\', now\(\)->year\);/', "function kpiDashboard(Request \$request)\n    {\n        \$defaultYear = \App\Models\Plan::where('status', 'approved')->max('year') ?? now()->year;\n        \$year   = (int) \$request->get('year', \$defaultYear);", $content);
    file_put_contents($file, $content);
    echo "Applied regex replacement.\n";
}

// Also update EmployeePerformanceController if it exists and uses now()->year
$file2 = 'c:\wamp64\www\iprpms\app\Http\Controllers\EmployeePerformanceController.php';
if (file_exists($file2)) {
    $content2 = file_get_contents($file2);
    $search2 = "\$year = \$request->get('year', now()->year);";
    $replace2 = "\$defaultYear = \App\Models\Plan::where('status', 'approved')->max('year') ?? now()->year;\n        \$year = \$request->get('year', \$defaultYear);";
    if (strpos($content2, $search2) !== false) {
        $content2 = str_replace($search2, $replace2, $content2);
        file_put_contents($file2, $content2);
        echo "Successfully updated EmployeePerformanceController year.\n";
    }
}
