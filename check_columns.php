<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns = DB::select('SHOW COLUMNS FROM strategic_objectives');
echo "Columns in strategic_objectives:\n";
foreach ($columns as $column) {
    echo "- {$column->Field} ({$column->Type})\n";
}
