<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plan = App\Models\Plan::find(3);
if ($plan) {
    echo "Plan Found: ID {$plan->id} for Dept {$plan->department_id} (Year: {$plan->year})\n";
    $items = App\Models\PlanItem::where('plan_id', $plan->id)->get();
    foreach($items as $i) {
        $obj = $i->strategicObjective;
        echo "Item: {$i->id} | Obj: {$obj->code} - {$obj->description}\n";
        echo "  Performers: " . json_encode($i->performers) . "\n";
        echo "  Experts: " . json_encode($i->experts) . "\n";
    }
}
