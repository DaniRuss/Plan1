<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$objs = \App\Models\StrategicObjective::whereNotNull('kpi_code')->get();
$output = [];

foreach($objs as $o) {
    if ($o->kpi_code !== 'K1' && $o->kpi_code !== 'K2') continue; // Only care about K1 and K2 for now
    
    $objData = [
        'id' => $o->id,
        'code' => $o->objective_code,
        'kpi' => $o->kpi_code,
        'planItems' => []
    ];
    
    $items = \App\Models\PlanItem::where('strategic_objective_id', $o->id)->with('plan')->get();

    foreach($items as $item) {
        $plan = $item->plan;
        
        $itemData = [
            'item_id' => $item->id,
            'plan_id' => $plan ? $plan->id : null,
            'plan_year' => $plan ? $plan->year : null,
            'plan_status' => $plan ? $plan->status : null,
            'performances' => []
        ];
        
        $perfs = \App\Models\PeriodicPerformance::where('plan_item_id', $item->id)->get();
        foreach($perfs as $perf) {
            $itemData['performances'][] = [
                'perf_id' => $perf->id,
                'period' => $perf->period,
                'actual_value' => $perf->actual_value
            ];
        }
        
        $objData['planItems'][] = $itemData;
    }
    
    $output[] = $objData;
}

file_put_contents('kpi_diag.json', json_encode($output, JSON_PRETTY_PRINT));
echo "JSON Dumped.\n";
