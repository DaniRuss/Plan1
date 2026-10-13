<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StrategicObjective;

$so = StrategicObjective::first();
if (!$so) {
    echo "No objectives found.\n";
    exit;
}

echo "Testing save for ID: {$so->id}\n";
$testValue = 123.45;
$testYear = "2020";

$so->update([
    'baseline_value' => $testValue,
    'baseline_year' => $testYear
]);

$reloaded = StrategicObjective::find($so->id);
if ($reloaded->baseline_value == $testValue && $reloaded->baseline_year == $testYear) {
    echo "SUCCESS: Baseline fields saved correctly.\n";
} else {
    echo "FAILURE: Baseline fields did NOT save.\n";
    echo "Value: {$reloaded->baseline_value}, Year: {$reloaded->baseline_year}\n";
}
