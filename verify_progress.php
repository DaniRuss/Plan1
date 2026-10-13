<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StrategicObjective;

function test($actual, $planned, $baseline, $direction, $expected) {
    $obj = new StrategicObjective();
    $obj->goal_direction = $direction;
    $obj->baseline_value = $baseline;
    
    $result = $obj->calculateProgress($actual, $planned, $baseline);
    $status = abs($result - $expected) < 0.01 ? "PASS" : "FAIL";
    
    echo "Dir: $direction | Base: $baseline | Plan: $planned | Act: $actual | Exp: $expected | Res: $result | $status\n";
}

echo "Testing Progress Calculation Logic:\n";
echo "----------------------------------\n";

// Increase Scenarios (Higher is Better)
test(80, 100, 50, 'increase', 60);   // (80-50)/(100-50) = 30/50 = 60%
test(120, 100, 50, 'increase', 100); // Capped at 100%
test(40, 100, 50, 'increase', 0);    // Clamped at 0%
test(100, 100, 100, 'increase', 100); // Baseline == Plan, Actual >= Plan -> 100%
test(90, 100, 100, 'increase', 90);   // Baseline == Plan, simple ratio -> 90%

// Decrease Scenarios (Lower is Better)
test(100, 70, 130, 'decrease', 50);  // (130-100)/(130-70) = 30/60 = 50%
test(60, 70, 130, 'decrease', 100);  // Better than target -> 100%
test(140, 70, 130, 'decrease', 0);   // Worse than baseline -> 0%
test(70, 70, 70, 'decrease', 100);   // Baseline == Plan, Actual <= Plan -> 100%
test(140, 70, 70, 'decrease', 50);    // Baseline == Plan, simple ratio (70/140) -> 50%

echo "----------------------------------\n";
