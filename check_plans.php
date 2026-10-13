<?php
define('LARAVEL_START', microtime(true));
require 'c:\wamp64\www\iprpms\vendor\autoload.php';
$app = require_once 'c:\wamp64\www\iprpms\bootstrap\app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$plans = \App\Models\Plan::all();
foreach ($plans as $p) {
    echo "Plan ID: {$p->id}, Year: {$p->year}, Status: {$p->status}\n";
}
