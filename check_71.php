<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StrategicObjective;
use App\Models\PlanItem;

$so = StrategicObjective::where('code', '7.1')->first();
if (!$so) {
    echo "Objective 7.1 not found.\n";
    exit;
}
echo "Objective 7.1 ID: {$so->id}, Base: {$so->baseline_value}\n";

$items = PlanItem::where('strategic_objective_id', $so->id)
    ->whereHas('plan', function($q) { $q->where('year', 2018); })
    ->get();

foreach ($items as $item) {
    echo "PlanItem ID: {$item->id}, Baseline: {$item->baseline_value}, Plan ID: {$item->plan_id}\n";
}
