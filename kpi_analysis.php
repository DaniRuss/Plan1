<?php
define('LARAVEL_START', microtime(true));
require 'c:\wamp64\www\iprpms\vendor\autoload.php';
$app = require_once 'c:\wamp64\www\iprpms\bootstrap\app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StrategicObjective;
use App\Models\PlanItem;
use App\Models\PeriodicPerformance;
use App\Models\Plan;

$kpiCode = 'K1';
$year = 2026;
$period = 'q1';

$objs = StrategicObjective::where('kpi_code', $kpiCode)->get();
echo "Found " . $objs->count() . " objectives for KPI: $kpiCode\n";

foreach ($objs as $obj) {
    echo "\n=== Objective: {$obj->code} - {$obj->description} (ID: {$obj->id}) ===\n";
    $planItems = PlanItem::where('strategic_objective_id', $obj->id)->get();
    echo "Total Plan Items: " . $planItems->count() . "\n";
    
    foreach ($planItems as $item) {
        $plan = Plan::find($item->plan_id);
        echo "  - PlanItem ID {$item->id} | Plan ID: {$item->plan_id} ";
        if ($plan) {
            echo " | Year: {$plan->year} | Status: {$plan->status}";
        } else {
            echo " | Plan missing!";
        }
        echo "\n";
        
        $perfs = PeriodicPerformance::where('plan_item_id', $item->id)->get();
        echo "    - Performances count: " . $perfs->count() . "\n";
        foreach ($perfs as $perf) {
            echo "      - Period: {$perf->period} | Actual: {$perf->actual_value}\n";
        }
    }
}

echo "\n=== Dashboard Query Test ===\n";
$dashboardObjs = StrategicObjective::where('kpi_code', $kpiCode)
    ->with([
        'planItems' => function ($q) use ($year) {
            $q->whereHas('plan', function ($p) use ($year) {
                // Let's print out what year and status are
                $p->where('year', $year)->where('status', 'approved');
            })->with(['performances', 'weeklyPerformances']);
        }
    ])
    ->get();

foreach ($dashboardObjs as $dbj) {
    echo "Dashboard loaded Obj ID {$dbj->id} with " . $dbj->planItems->count() . " plan items (filtered by year=$year, status=approved)\n";
    foreach ($dbj->planItems as $item) {
        $p = $item->performances->where('period', $period)->first();
        echo "  - Item ID {$item->id} -> $period performance: ";
        if ($p) echo $p->actual_value . "\n";
        else echo "NOT FOUND\n";
    }
}
