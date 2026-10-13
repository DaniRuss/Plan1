<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

$roleName = 'department_chief';
$role = Role::where('name', $roleName)->first();

if ($role) {
    echo "Role: {$roleName}\n";
    echo "Permissions:\n";
    foreach ($role->permissions as $p) {
        echo "- {$p->name}\n";
    }
} else {
    echo "Role {$roleName} not found.\n";
}

$permission = 'submit_plan';
$p = Permission::where('name', $permission)->first();
if ($p) {
    echo "\nPermission {$permission} exists.\n Roles with this permission:\n";
    foreach ($p->roles as $r) {
        echo "- {$r->name}\n";
    }
} else {
    echo "\nPermission {$permission} does NOT exist in DB.\n";
}
