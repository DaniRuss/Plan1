<?php
define('LARAVEL_START', microtime(true));
require 'c:\wamp64\www\iprpms\vendor\autoload.php';
$app = require_once 'c:\wamp64\www\iprpms\bootstrap\app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\EdrReportController;
use Illuminate\Http\Request;

$controller = $app->make(EdrReportController::class);
$request = new Request();
// Do not pass year so it uses the new default
$response = $controller->kpiDashboard($request);

$kpiGroups = $response->getData()['kpiGroups'] ?? [];
$year = $response->getData()['year'] ?? 'UNKNOWN';

echo "KPI Dashboard Test\n";
echo "Resolved Default Year: $year\n";
echo "Number of KPI Groups: " . count($kpiGroups) . "\n";

foreach ($kpiGroups as $kpi => $objs) {
    if ($kpi == 'K1') {
        echo "Found K1\n";
        foreach ($objs as $obj) {
            echo "  - Obj ID {$obj->id} has " . $obj->planItems->count() . " plan items.\n";
            foreach ($obj->planItems as $item) {
                echo "    - Item {$item->id} -> default Q1 perf is " . $item->performances->where('period', 'q1')->first()?->actual_value . "\n";
            }
        }
    }
}
