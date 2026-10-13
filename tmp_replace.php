<?php
$content = file_get_contents('app/Http/Controllers/StrategicObjectiveController.php');

$content = preg_replace(
    '/\'parent_department_id\'\s*=>\s*\'required\|exists:departments,id\',\s*\'department_id\'\s*=>\s*\'nullable\|exists:departments,id\',\s*\'parent_id\'\s*=>\s*\'nullable\|exists:strategic_objectives,id\',\s*\'performers\'\s*=>\s*\'nullable\|array\',\s*\'performers\.\*\'\s*=>\s*\'exists:departments,id\',/m',
    '\'parent_id\' => \'nullable|exists:strategic_objectives,id\',
            \'performers\' => \'required|array|min:1\',
            \'performers.*\' => \'exists:departments,id\',',
    $content
);

$content = preg_replace(
    '/\/\/ Set department and performers based on hierarchy\s*\$parentId = \$validated\[\'parent_department_id\'\];\s*\$childId  = \$validated\[\'department_id\'\] \?\? null;\s*\$validated\[\'department_id\'\] = \$childId \?\: \$parentId;\s*if \(\!isset\(\$validated\[\'performers\'\]\) \|\| empty\(\$validated\[\'performers\'\]\)\) \{\s*\$validated\[\'performers\'\] = \$childId \? \[\(int\)\$parentId, \(int\)\$childId\] : \[\(int\)\$parentId\];\s*\}/m',
    '// The objective is owned by the creator\'s department
        $validated[\'department_id\'] = auth()->user() ? auth()->user()->department_id : null;
        $validated[\'performers\'] = array_map(\'intval\', $validated[\'performers\']);',
    $content
);

file_put_contents('app/Http/Controllers/StrategicObjectiveController.php', $content);
echo "Replacement executed.";
