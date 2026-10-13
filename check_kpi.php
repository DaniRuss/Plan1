<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$objs = \App\Models\StrategicObjective::whereNotNull('kpi_code')->get();

foreach($objs as $o) {
    echo "Objective ID: {$o->id} | Code: {$o->objective_code} | KPI: {$o->kpi_code}\n";
    
    // Check all plan items regardless of plan status/year
    $items = \App\Models\PlanItem::where('strategic_objective_id', $o->id)->with('plan')->get();
    
    echo "  -> Total plan items in DB: " . $items->count() . "\n";

    foreach($items as $item) {
        $plan = $item->plan;
        echo "    -> Plan Item ID: {$item->id} | Plan ID: " . ($plan ? $plan->id : 'none') . " | Plan Year: " . ($plan ? $plan->year : 'N/A') . " | Plan Status: " . ($plan ? $plan->status : 'N/A') . "\n";
        
        $perfs = \App\Models\PeriodicPerformance::where('plan_item_id', $item->id)->get();
        echo "       Performances count: " . $perfs->count() . "\n";
        
        foreach($perfs as $perf) {
            echo "       * Perf ID {$perf->id} | Period: '{$perf->period}' | Actual: {$perf->actual_value}\n";
        }
    }
}
