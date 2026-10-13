<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\StrategicObjective;
use App\Models\Department;
use App\Models\User;

$res = "";
$objectives = StrategicObjective::where('code', 'LIKE', '8%')->get();
foreach ($objectives as $o) {
    $res .= "ID: {$o->id}, Code: {$o->code}, Status: {$o->status}, DepartmentID: {$o->department_id}, Perf: " . json_encode($o->performers) . PHP_EOL;
}

$planningDept = Department::where('name', 'LIKE', '%Planning%')->first();
if ($planningDept) {
    $res .= "Planning Dept ID: {$planningDept->id}\n";
}

$chief = User::where('name', 'LIKE', '%Planning%')->first();
if ($chief) {
    $res .= "Chief User: {$chief->name}, ID: {$chief->id}, DeptID: {$chief->department_id}, Roles: " . json_encode($chief->getRoleNames()) . "\n";
}

file_put_contents('debug_results.txt', $res);
echo "Done\n";
