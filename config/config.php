<?php
// ── SYSTEM SECURITY HARDENING ──────────────────────────────────────
// Robust HTTPS detection supporting Reverse Proxies, Cloudflare, AWS, etc.
$is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// 1. Session Security: Protect against XSS and Session Hijacking
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => null,
        'secure'   => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Error Reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

// 2. Security Headers: Prevent Clickjacking and MIME-sniffing
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
if ($is_secure) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// 3. Error Handling: Prevent Database/Server path leakage
$isDebug = (getenv('APP_DEBUG') === 'true');
if (!$isDebug) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..'));
}

// ── Added BASE_URL for localhost support ──────────────────────────
if (!defined('BASE_URL')) {
    $_protocol = $is_secure ? "https://" : "http://";
    $_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $_dir = str_replace('\\', '/', dirname($_script));
    if (strpos($_script, '/config/') !== false) {
        $_dir = dirname($_dir);
    }
    $_base_url = $_protocol . $_host . rtrim($_dir, '/');
    define('BASE_URL', $_base_url);
}

$localConfig = [];$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$appTimezone = getenv('APP_TIMEZONE') ?: ($localConfig['timezone'] ?? 'Asia/Manila');
date_default_timezone_set($appTimezone);

$host = $localConfig['host'] ?? getenv('DB_HOST') ?: 'localhost';
$port = (int) ($localConfig['port'] ?? getenv('DB_PORT') ?: 3306);
$user = $localConfig['user'] ?? getenv('DB_USER') ?: 'root';
$pass = $localConfig['pass'] ?? (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$db   = $localConfig['db']   ?? getenv('DB_NAME') ?: 'cathedral';

// Mail Configuration
// Intentionally do not ship fallback SMTP credentials in source control.
$smtpHost     = getenv('SMTP_HOST') ?: ($localConfig['smtp_host'] ?? '');
$smtpUser     = getenv('SMTP_USER') ?: ($localConfig['smtp_user'] ?? '');
$smtpPass     = getenv('SMTP_PASS');
if ($smtpPass === false) {
    $smtpPass = $localConfig['smtp_pass'] ?? '';
}
$smtpSecure   = getenv('SMTP_SECURE') ?: ($localConfig['smtp_secure'] ?? 'tls');
$smtpPort     = (int) (getenv('SMTP_PORT') ?: ($localConfig['smtp_port'] ?? 587));
$smtpFromName = getenv('SMTP_FROM_NAME') ?: ($localConfig['smtp_from_name'] ?? 'IFI Cathedral System');
$smtpEnabled  = !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass);

// SMS Configuration
$smsProvider   = getenv('SMS_PROVIDER') ?: ($localConfig['sms_provider'] ?? 'mock');
$smsApiKey     = getenv('SMS_API_KEY');
if ($smsApiKey === false) {
    $smsApiKey = $localConfig['sms_api_key'] ?? '';
}
$smsSenderName = getenv('SMS_SENDER_NAME') ?: ($localConfig['sms_sender_name'] ?? 'SEMAPHORE');
$smsBaseUrl    = getenv('SMS_BASE_URL') ?: ($localConfig['sms_base_url'] ?? 'https://api.semaphore.co/api/v4/messages');
$smsCountry    = getenv('SMS_COUNTRY') ?: ($localConfig['sms_country'] ?? 'PH');
$smsEnabled    = $smsProvider !== 'mock' && !empty($smsApiKey);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if ($conn === false) {
    throw new Exception('Database Connection failed: unable to initialize mysqli');
}

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
$connected = @$conn->real_connect($host, $user, $pass, $db, $port);

// Automatic local port fallback (supports environments switching between 3306 and 3307, or remote fallback)
if (!$connected || $conn->connect_error) {
    if (in_array($host, ['localhost', '127.0.0.1'], true)) {
        $altPort = ($port === 3307) ? 3306 : 3307;
        $connAlt = mysqli_init();
        if ($connAlt) {
            $connAlt->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            if (@$connAlt->real_connect($host, $user, $pass, $db, $altPort) && !$connAlt->connect_error) {
                $conn = $connAlt;
                $connected = true;
                $port = $altPort;
            }
        }
    } else {
        // Fallback to local database if remote host is unreachable (e.g. running local testing/dev)
        foreach ([3307, 3306] as $lp) {
            $connLocal = mysqli_init();
            if ($connLocal) {
                $connLocal->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
                foreach ([$db, 'cathedral'] as $candidateDb) {
                    if (@$connLocal->real_connect('127.0.0.1', 'root', '', $candidateDb, $lp) && !$connLocal->connect_error) {
                        $conn = $connLocal;
                        $connected = true;
                        $host = '127.0.0.1';
                        $port = $lp;
                        $user = 'root';
                        $pass = '';
                        $db   = $candidateDb;
                        break 2;
                    }
                }
            }
        }
    }
}

if (!$connected || $conn->connect_error) {
    throw new Exception('Database Connection failed: ' . $conn->connect_error);
}


if (!$conn->set_charset('utf8mb4')) {
    throw new Exception('Database Connection failed: unable to set utf8mb4 charset');
}

// ── GLOBAL PARISH INFO ─────────────────────────────────────────────
// Fetch once per request to avoid redundant queries in views/receipts
$parish_info = [
    'parish_name' => 'Cathedral of St. Michael the Archangel',
    'diocese' => 'Iglesia Filipina Independiente · Diocese of Cavite',
    'address' => '064 Prof, H.F. Rubio St, Digman, Bacoor City, Cavite',
    'phone' => '(02) 8123-4567',
    'email' => 'cathedral@ifi.org.ph'
];

try {
    $pi_res = $conn->query("SELECT * FROM parish_contact_info ORDER BY info_id DESC LIMIT 1");
    if ($pi_res && $pi_res->num_rows > 0) {
        $parish_info = array_merge($parish_info, $pi_res->fetch_assoc());
    }
} catch (Throwable $e) {
    // Silent fail, keep defaults
}

define('PARISH_NAME', !empty($parish_info['parish_name']) && $parish_info['parish_name'] !== 'IFI Cathedral' ? $parish_info['parish_name'] : 'Cathedral of St. Michael the Archangel');
define('DIOCESE_NAME', 'Iglesia Filipina Independiente · Diocese of Cavite');
