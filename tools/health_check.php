<?php
declare(strict_types=1);

/**
 * Quick system health checks:
 * - DB connectivity
 * - Login lockout schema
 * - Login throttle storage
 * - SMTP configuration/dependencies
 * - Optional SMTP send test
 *
 * Usage:
 *   php tools/health_check.php
 *   php tools/health_check.php --send-test=you@example.com
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/mail/mailer.php';

$sendTestTo = null;
$fixLoginColumns = false;
foreach ($argv as $arg) {
    if (strpos($arg, '--send-test=') === 0) {
        $sendTestTo = trim(substr($arg, 12));
    }
    if ($arg === '--fix-login-columns') {
        $fixLoginColumns = true;
    }
}

$results = [];

$results[] = [
    'name' => 'Database Connection',
    'ok' => isset($conn) && $conn instanceof mysqli,
    'detail' => isset($conn) && $conn instanceof mysqli ? 'Connected' : 'No mysqli connection',
];

$hasAttempts = false;
$hasLockedUntil = false;
if (isset($conn) && $conn instanceof mysqli) {
    if ($fixLoginColumns) {
        $clauses = [];
        $checkAttempts = $conn->query("SHOW COLUMNS FROM users LIKE 'login_attempts'");
        $attemptsExists = $checkAttempts instanceof mysqli_result && $checkAttempts->num_rows > 0;
        if ($checkAttempts instanceof mysqli_result) {
            $checkAttempts->close();
        }

        $checkLocked = $conn->query("SHOW COLUMNS FROM users LIKE 'locked_until'");
        $lockedExists = $checkLocked instanceof mysqli_result && $checkLocked->num_rows > 0;
        if ($checkLocked instanceof mysqli_result) {
            $checkLocked->close();
        }

        if (!$attemptsExists) {
            $clauses[] = "ADD COLUMN login_attempts INT UNSIGNED NOT NULL DEFAULT 0";
        }
        if (!$lockedExists) {
            $clauses[] = "ADD COLUMN locked_until DATETIME NULL DEFAULT NULL";
        }
        if (!empty($clauses)) {
            $conn->query('ALTER TABLE users ' . implode(', ', $clauses));
        }
    }

    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'login_attempts'");
    $hasAttempts = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }

    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'locked_until'");
    $hasLockedUntil = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
}

$results[] = [
    'name' => 'Login Lockout Columns',
    'ok' => $hasAttempts && $hasLockedUntil,
    'detail' => 'login_attempts=' . ($hasAttempts ? 'yes' : 'no') . ', locked_until=' . ($hasLockedUntil ? 'yes' : 'no'),
];

$throttleDir = dirname(__DIR__) . '/tmp/auth_throttle';
if (!is_dir($throttleDir)) {
    @mkdir($throttleDir, 0755, true);
}
$results[] = [
    'name' => 'Throttle Storage Directory',
    'ok' => is_dir($throttleDir) && is_writable($throttleDir),
    'detail' => $throttleDir,
];

$smtpConfigured = !empty($smtpEnabled) && !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass) && !empty($smtpPort);
$smtpUserMasked = '';
if (!empty($smtpUser)) {
    $parts = explode('@', (string) $smtpUser, 2);
    $namePart = $parts[0] ?? '';
    $domainPart = $parts[1] ?? '';
    $smtpUserMasked = ($namePart !== '' ? substr($namePart, 0, 2) . '***' : '***') . ($domainPart !== '' ? '@' . $domainPart : '');
}

$results[] = [
    'name' => 'SMTP Config Present',
    'ok' => $smtpConfigured,
    'detail' => 'host=' . ($smtpHost ?: 'missing') . ', user=' . ($smtpUserMasked ?: 'missing') . ', port=' . ($smtpPort ?: 'missing'),
];

$deps = [
    dirname(__DIR__) . '/src/Exception.php',
    dirname(__DIR__) . '/src/PHPMailer.php',
    dirname(__DIR__) . '/src/SMTP.php',
];
$missingDeps = array_values(array_filter($deps, static fn($p) => !is_file($p)));
$results[] = [
    'name' => 'Mailer Dependencies',
    'ok' => count($missingDeps) === 0,
    'detail' => count($missingDeps) === 0 ? 'All dependencies found' : ('Missing: ' . implode(', ', $missingDeps)),
];

if ($sendTestTo !== null) {
    if (!filter_var($sendTestTo, FILTER_VALIDATE_EMAIL)) {
        $results[] = [
            'name' => 'SMTP Send Test',
            'ok' => false,
            'detail' => 'Invalid --send-test email',
        ];
    } elseif (!$smtpConfigured) {
        $results[] = [
            'name' => 'SMTP Send Test',
            'ok' => false,
            'detail' => 'Skipped: SMTP not configured',
        ];
    } else {
        $sent = sendChurchEmail(
            $sendTestTo,
            'Health Check',
            'IFI Cathedral SMTP Health Check',
            '<p>This is a test email from <strong>tools/health_check.php</strong>.</p>'
        );
        $results[] = [
            'name' => 'SMTP Send Test',
            'ok' => (bool) $sent,
            'detail' => $sent ? ('Sent to ' . $sendTestTo) : ('Failed to send to ' . $sendTestTo),
        ];
    }
}

$allOk = true;
echo "=== IFI Cathedral System Health Check ===\n";
foreach ($results as $row) {
    $status = $row['ok'] ? 'PASS' : 'FAIL';
    if (!$row['ok']) {
        $allOk = false;
    }
    echo sprintf("[%s] %s - %s\n", $status, $row['name'], $row['detail']);
}

echo $allOk ? "Overall: PASS\n" : "Overall: ATTENTION NEEDED\n";
exit($allOk ? 0 : 1);
