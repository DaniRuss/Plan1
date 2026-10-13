<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = ['logistics_companies', 'train_journeys', 'cargo_consignments', 'cargo_movement_logs', 'migrations'];

foreach ($tables as $table) {
    echo "$table: " . (Schema::hasTable($table) ? 'EXISTS' : 'MISSING') . PHP_EOL;
    if (Schema::hasTable($table) && $table === 'migrations') {
        $count = \DB::table($table)->where('migration', 'like', '%2026_03_28%')->count();
        echo " - Number of 2026_03_28 migrations in table: $count" . PHP_EOL;
    }
}
