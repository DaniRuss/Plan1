<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StrategicObjective;

$parents = StrategicObjective::whereNull('parent_id')->get();
foreach ($parents as $parent) {
    if ($parent->status !== 'draft') {
        foreach ($parent->children as $child) {
            $child->update(['status' => $parent->status]);
            foreach ($child->children as $grandchild) {
                $grandchild->update(['status' => $parent->status]);
            }
        }
    }
}
echo "Synced successfully.";
