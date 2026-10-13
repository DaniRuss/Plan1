<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plannings = App\Models\Department::where('name', 'like', '%Planning%')->get();
foreach($plannings as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Parent: {$p->parent_id}\n";
    $children = App\Models\Department::where('parent_id', $p->id)->get();
    if($children->count() > 0) {
        echo "  CHILDREN:\n";
        foreach($children as $c) {
             echo "    ID: {$c->id} | Name: {$c->name}\n";
        }
    } else {
        echo "  NO CHILDREN FOUND\n";
    }
}
