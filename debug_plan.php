<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- DEPARTMENTS ---\n";
$depts = App\Models\Department::all();
foreach($depts as $d) {
    echo "ID: {$d->id} | Name: {$d->name} | Parent: {$d->parent_id}\n";
}

echo "\n--- PLAN ITEMS FOR PLAN 2018 (if exists) ---\n";
// Assuming plan ID can be found from department 25 or similar
$plan = App\Models\Plan::where('year', 2018)->first();
if ($plan) {
    echo "Plan Found: ID {$plan->id} for Dept {$plan->department_id}\n";
    $items = App\Models\PlanItem::where('plan_id', $plan->id)->get();
    foreach($items as $i) {
        echo "Item ID: {$i->id} | Obj ID: {$i->strategic_objective_id} | Performers: " . json_encode($i->performers) . " | Experts: " . json_encode($i->experts) . "\n";
    }
} else {
    echo "Plan 2018 not found in DB\n";
}
