<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StrategicObjective;
use App\Models\PlanItem;
use App\Http\Controllers\MasterReportController;

$controller = new MasterReportController();
$so = StrategicObjective::where('code', '7.1')->first();
$allPlanItems = PlanItem::where('strategic_objective_id', $so->id)
    ->whereHas('plan', function($q) { $q->where('year', 2018); })
    ->get()
    ->groupBy('strategic_objective_id');

$reflection = new \ReflectionClass(MasterReportController::class);
$method = $reflection->getMethod('formatObjectiveRow');
$method->setAccessible(true);

$row = $method->invoke($controller, $so, $allPlanItems, false);

echo "Objective 7.1 Baseline in Report: " . $row['baseline'] . "\n";
if ($row['baseline'] == $so->baseline_value) {
    echo "SUCCESS: Baseline fallback working.\n";
} else {
    echo "FAILURE: Baseline still 0 or incorrect.\n";
}

// Test with NO items (showAllObjs = true)
$rowAll = $method->invoke($controller, $so, collect(), true);
echo "Objective 7.1 Baseline (No items): " . $rowAll['baseline'] . "\n";
if ($rowAll['baseline'] == $so->baseline_value) {
    echo "SUCCESS: Baseline fallback working for empty items.\n";
}
