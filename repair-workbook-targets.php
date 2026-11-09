<?php
/**
 * cPanel Terminal: php repair-workbook-targets.php "/path/to/original.xlsx" 2026 [--apply]
 * Place beside artisan. Requires the updated importer and RestoreWorkbookTargets command.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from cPanel Terminal or SSH.');
}

$args = array_slice($argv, 1);
if (count($args) < 2 || count($args) > 3 || !ctype_digit($args[1])
    || (isset($args[2]) && $args[2] !== '--apply')) {
    fwrite(STDERR, "Usage: php repair-workbook-targets.php \"/path/to/original.xlsx\" 2026 [--apply]\nWithout --apply, only a preview is shown.\n");
    exit(1);
}
$workbook = realpath($args[0]);
if (!$workbook || !is_file($workbook) || !is_readable($workbook)) {
    fwrite(STDERR, "Workbook does not exist or cannot be read.\n");
    exit(1);
}
foreach (['vendor/autoload.php', 'bootstrap/app.php', 'app/Services/ObjectiveWorkbookImport.php', 'app/Console/Commands/RestoreWorkbookTargets.php'] as $required) {
    if (!is_file(__DIR__.'/'.$required)) {
        fwrite(STDERR, "Missing {$required}. Upload this script beside artisan and deploy the updated importer and repair command.\n");
        exit(1);
    }
}
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$input = ['command' => 'objectives:restore-workbook-targets', 'file' => $workbook, '--year' => $args[1], '--no-interaction' => true];
if (($args[2] ?? '') === '--apply') $input['--apply'] = true;
exit($app->handleCommand(new \Symfony\Component\Console\Input\ArrayInput($input)));
