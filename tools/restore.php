<?php
/**
 * Restore runner (CLI only)
 *
 * Usage:
 *   php tools/restore.php --file=backup_YYYYmmdd_HHMMSS.zip
 *   php tools/restore.php --file="C:\path\backup_....zip" --no-storage
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/services/BackupService.php';
require_once __DIR__ . '/../app/models/AuditLogger.php';

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $pair = explode('=', substr($arg, 2), 2);
        $args[$pair[0]] = $pair[1] ?? true;
    }
}

$file = (string)($args['file'] ?? '');
if ($file === '') {
    echo "Missing --file argument\n";
    exit(1);
}

$restoreStorage = !isset($args['no-storage']);

$dbConfig = [
    'host' => $host,
    'port' => $port,
    'user' => $user,
    'pass' => $pass,
    'db' => $db,
];

$service = new BackupService($conn, BASE_PATH, $dbConfig);
$result = $service->restoreBackup($file, $restoreStorage);

AuditLogger::log(
    $conn,
    'restored',
    'System',
    "Restored database from backup: {$file}",
    null,
    ['file' => $file, 'restore_storage' => $restoreStorage, 'result' => $result],
    0,
    'system-cli',
    'System'
);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($result['ok'] ?? false) ? 0 : 1);

