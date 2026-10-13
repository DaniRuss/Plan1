<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::where('name', 'like', '%Planning%')->get();
echo "--- PLANNERS ---\n";
foreach($users as $u) {
    echo "ID: " . $u->id . "\n";
    echo "Name: " . $u->name . "\n";
    echo "Email: " . $u->email . "\n";
    echo "Dept: " . $u->department_id . "\n";
    echo "-----------------\n";
}
