<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/services/BackupService.php';
    require_once __DIR__ . '/../app/models/AuditLogger.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$role = (string)($_SESSION['role'] ?? '');
if ($role !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit();
}

$token = (string)($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
$actual = (string)($_SESSION['csrf'] ?? '');
if ($actual === '' || !hash_equals($actual, $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
    exit();
}

$file = (string)($_POST['backup_file'] ?? '');
if ($file === '' || !BackupService::isValidBackupFileName($file)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid backup file']);
    exit();
}

$restoreStorage = isset($_POST['restore_storage']) && (string)$_POST['restore_storage'] === '1';

$dbConfig = [
    'host' => $host,
    'port' => $port,
    'user' => $user,
    'pass' => $pass,
    'db' => $db,
];

$service = new BackupService($conn, BASE_PATH, $dbConfig);
$result = $service->restoreBackup($file, $restoreStorage);

AuditLogger::logFromSession(
    $conn,
    'restored',
    'System',
    "Restored database from backup: {$file}",
    null,
    ['file' => $file, 'restore_storage' => $restoreStorage, 'result' => $result]
);

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
exit();
