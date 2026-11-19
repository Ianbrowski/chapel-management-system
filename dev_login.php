<?php
/**
 * Developer quick session switcher for browser verification
 * Strictly restricted to local development environments with APP_DEBUG enabled.
 */
require_once __DIR__ . '/config/config.php';

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$isDebug = (getenv('APP_DEBUG') === 'true');

if (!$isLocal || !$isDebug) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden: Developer gateway is strictly disabled in this environment.');
}

if (session_status() === PHP_SESSION_NONE) session_start();

$role = $_GET['role'] ?? 'Admin';
$sec  = $_GET['section'] ?? 'events';
$tab  = $_GET['tab'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE role = ? LIMIT 1");
$stmt->bind_param("s", $role);
$stmt->execute();
$res = $stmt->get_result();
$u   = $res->fetch_assoc();

if (!$u) {
    $res = $conn->query("SELECT * FROM users LIMIT 1");
    $u   = $res->fetch_assoc();
}

$_SESSION['user_id']   = (int)$u['id'];
$_SESSION['username']  = $u['user'] ?? $u['username'];
$_SESSION['role']      = $u['role'];
$_SESSION['full_name'] = !empty($u['full_name']) ? $u['full_name'] : ($u['user'] ?? $u['username']);
$_SESSION['chapel_id'] = $u['chapel_id'] ?? null;
$_SESSION['login_at']  = time();
require_once __DIR__ . '/app/controllers/AuthController.php';
$_SESSION['csrf']      = bin2hex(random_bytes(32));
$_SESSION['_fp']       = AuthController::makeFingerprint();

$url = "dashboard.php?section=" . urlencode($sec);
if ($tab !== '') {
    $url .= "&tab=" . urlencode($tab);
}
header("Location: " . $url);
exit();
