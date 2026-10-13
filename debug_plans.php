<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plans = App\Models\Plan::where('year', 2018)->with('department')->get()->toArray();
file_put_contents('plans.json', json_encode($plans, JSON_PRETTY_PRINT));
echo "Done\n";
