<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\Permission;

$roles_to_fix = ['department_chief', 'dept_head', 'department_directorate'];
$perms_to_add = ['submit_plan', 'edit_plan', 'view_plans', 'create_plan'];

foreach ($roles_to_fix as $rName) {
    $role = Role::where('name', $rName)->first();
    if ($role) {
        echo "Updating role: {$rName}\n";
        foreach ($perms_to_add as $pName) {
            $p = Permission::firstOrCreate(['name' => $pName]);
            if (!$role->permissions()->where('permissions.id', $p->id)->exists()) {
                $role->permissions()->attach($p->id);
                echo "  + Attached {$pName}\n";
            } else {
                echo "  . Already has {$pName}\n";
            }
        }
    }
}
echo "Permission fix complete.\n";
