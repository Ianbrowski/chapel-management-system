<?php
declare(strict_types=1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/services/BackupService.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Backup module unavailable: ' . $e->getMessage();
    exit();
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
if ($role !== 'Admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

$file = (string)($_GET['file'] ?? '');
if ($file === '' || !BackupService::isValidBackupFileName($file)) {
    http_response_code(400);
    echo 'Invalid backup file.';
    exit();
}

$path = BASE_PATH . '/backups/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    echo 'Backup file not found.';
    exit();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit();
