<?php
$file = 'app/Http/Controllers/StrategicObjectiveController.php';
$content = file_get_contents($file);

$search1 = "        if (!isset(\$validated['performers'])) {";
$replace1 = "        if (!isset(\$validated['performers']) || empty(\$validated['performers'])) {";

$search2 = "            \$validated['performers'] = \$childId ? [(int)\$parentId, (int)\$childId] : [(int)\$parentId];\n        }";
$replace2 = "            \$validated['performers'] = \$childId ? [(int)\$parentId, (int)\$childId] : [(int)\$parentId];\n        } else {\n            \$validated['performers'] = array_map('intval', \$validated['performers']);\n        }";

// Apply generic check missing `empty` since step 134 command might have succeeded
$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "Patched successfully.\n";
