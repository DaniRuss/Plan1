<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CalendarHelper;

$year = 2026;
$month = 'july';

echo "Verification for $month $year:\n";
$weeks = CalendarHelper::getMonthWeeks($month, $year);
echo "Total Weeks: " . count($weeks) . "\n";

foreach ($weeks as $w => $info) {
    echo "Week $w: {$info['label']} ({$info['start']} to {$info['end']})\n";
}

// Check navigation logic (Entry view)
$weeksInMonth = CalendarHelper::getMonthWeeks($month, $year);
echo "\nNavigation Selector Check:\n";
foreach ($weeksInMonth as $w => $info) {
    echo "Button W$w -> Title: {$info['label']}\n";
}
