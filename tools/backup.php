<?php
/**
 * Manual backup runner
 *
 * Usage (CLI):
 *   php tools/backup.php
 *
 * Usage (Web, admin only):
 *   POST /tools/backup.php with csrf
 */

declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/services/BackupService.php';
    require_once __DIR__ . '/../app/models/AuditLogger.php';
} catch (Throwable $e) {
    $payload = ['ok' => false, 'error' => $e->getMessage()];
    if (PHP_SAPI === 'cli') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit();
}

$dbConfig = [
    'host' => $host,
    'port' => $port,
    'user' => $user,
    'pass' => $pass,
    'db' => $db,
];

$service = new BackupService($conn, BASE_PATH, $dbConfig);

if (PHP_SAPI === 'cli') {
    $kind = 'daily';
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--kind=')) {
            $kind = strtolower((string)substr($arg, 7));
        }
    }

    $isWeekly = ($kind === 'weekly');
    $result = $service->createBackup(
        $isWeekly ? 'weekly' : 'cli',
        0,
        'system-cli',
        $isWeekly ? 52 : 14,
        $isWeekly ? 'weekly_restore_point' : 'backup'
    );
    AuditLogger::log(
        $conn,
        'created',
        'System',
        "Backup: " . ($result['file_name'] ?? 'Failed Backup'),
        null,
        $result,
        0,
        'system-cli',
        'System'
    );
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

$role = (string)($_SESSION['role'] ?? '');
$allowedRoles = ['Admin'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit();
}

$token = (string)($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
$actual = (string)($_SESSION['csrf'] ?? '');
if ($actual === '' || !hash_equals($actual, $token)) {
    http_response_code(419);
    echo 'CSRF validation failed';
    exit();
}

$kind = strtolower((string)($_POST['backup_kind'] ?? 'daily'));
$isWeekly = ($kind === 'weekly');
$result = $service->createBackup(
    $isWeekly ? 'weekly-web' : 'web',
    (int)$_SESSION['user_id'],
    (string)($_SESSION['username'] ?? 'unknown'),
    $isWeekly ? 52 : 14,
    $isWeekly ? 'weekly_restore_point' : 'backup'
);
AuditLogger::logFromSession(
    $conn,
    'created',
    'System',
    "Backup: " . ($result['file_name'] ?? 'Failed Backup'),
    null,
    $result
);
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
