<?php
require_once __DIR__ . '/BaseController.php';

/**
 * AuthController — Hardened Authentication
 *
 * Security features:
 *  - CSRF token validation on all POST actions
 *  - Constant-time response (prevents user enumeration)
 *  - Session fixation prevention (session_regenerate_id)
 *  - Session fingerprinting (IP + User-Agent binding)
 *  - Dual-layer brute force protection (DB lock + file-based throttle)
 *  - bcrypt cost auto-upgrade on successful login
 *  - Legacy MD5/SHA1 plain-text migration on login
 *  - Full audit log on success, failure, and logout
 *  - Security headers emitted on auth responses
 */
class AuthController extends BaseController {

    private ?bool $supportsLoginLocking = null;

    // Throttle: 5 failures within 15 minutes per account → lock
    private const THROTTLE_WINDOW_SECONDS = 900;
    private const THROTTLE_MAX_ATTEMPTS   = 5;

    // Global IP Throttle: 10 failures across any username within 10 minutes → lock IP
    private const IP_THROTTLE_WINDOW_SECONDS = 600;
    private const IP_THROTTLE_MAX_ATTEMPTS   = 10;

    // bcrypt: always use cost >= 12; re-hash if stored cost is lower
    private const BCRYPT_COST = 12;

    // Max input lengths to prevent DoS via huge payloads (username/email <= 100, bcrypt max password <= 72)
    private const MAX_IDENTITY_LEN = 100;
    private const MAX_PASSWORD_LEN = 72;

    public function __construct($conn) {
        parent::__construct($conn, false);
        $this->sendSecurityHeaders();
    }

    // ─── SECURITY HEADERS ────────────────────────────────────────────────────
    private function sendSecurityHeaders(): void {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }

    // ─── LOGIN ───────────────────────────────────────────────────────────────
    private function isSuperAdminChannel(): bool {
        return (($_POST['login_channel'] ?? '') === 'super_admin');
    }

    private function validateSuperAdminToken(): bool {
        $expected = (string)($_SESSION['admin_login_token'] ?? '');
        $provided = (string)($_POST['admin_portal_token'] ?? '');
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    private function respondLoginError(string $errorCode, string $errorMessage, array $extraParams = [], int $httpStatus = 401): void {
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpStatus);
            $payload = [
                'success' => false,
                'error'   => $errorMessage,
                'code'    => $errorCode
            ];
            foreach ($extraParams as $k => $v) {
                $payload[$k] = $v;
            }
            echo json_encode($payload);
            exit();
        }
        $query = 'error=' . urlencode($errorCode);
        foreach ($extraParams as $k => $v) {
            $query .= '&' . urlencode($k) . '=' . urlencode((string)$v);
        }
        $targetPage = $this->isSuperAdminChannel() ? '../../admin_login.php?' : '../../index.php?';
        $this->redirect($targetPage . $query);
    }

    // ─── LOGIN ───────────────────────────────────────────────────────────────
    public function login(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('../../index.php?open_login=1');
        }

        // 1. CSRF guard
        if (!$this->validateCsrf($_POST['csrf'] ?? $_POST['csrf_token'] ?? '')) {
            $this->respondLoginError('csrf', 'Security validation failed. Please refresh and try again.');
        }

        $isSuperAdminChannel = $this->isSuperAdminChannel();
        if ($isSuperAdminChannel && !$this->validateSuperAdminToken()) {
            $this->respondLoginError('csrf', 'Super Admin gateway token expired. Please refresh and try again.');
        }

        // 2. Input sanitization + length guards (DoS prevention)
        $rawUser = (string)($_POST['user'] ?? '');
        $rawPass = (string)($_POST['password'] ?? '');

        if (mb_strlen($rawUser) > self::MAX_IDENTITY_LEN) {
            $this->respondLoginError('len_user', 'Username or email cannot exceed ' . self::MAX_IDENTITY_LEN . ' characters.');
        }
        if (mb_strlen($rawPass) > self::MAX_PASSWORD_LEN) {
            $this->respondLoginError('len_pass', 'Password cannot exceed ' . self::MAX_PASSWORD_LEN . ' characters.');
        }

        $identity = substr(trim($rawUser), 0, self::MAX_IDENTITY_LEN);
        $password = substr(trim($rawPass), 0, self::MAX_PASSWORD_LEN);

        if ($identity === '' && $password === '') { 
            $this->respondLoginError('empty_all', 'Please enter both username and password.'); 
        }
        if ($identity === '') { 
            $this->respondLoginError('empty_user', 'Username or email address is required.'); 
        }
        if ($password === '') { 
            $this->respondLoginError('empty_pass', 'Password is required.'); 
        }

        // 3. File-based throttle checks
        // 3a. Global IP-wide throttle check (defends against credential-stuffing/password spraying)
        $ipWaitSeconds = $this->getIpThrottleWaitSeconds();
        if ($ipWaitSeconds > 0) {
            $wait = (int) ceil($ipWaitSeconds / 60);
            $this->auditLog('ip_throttle_block', "Blocked login attempt from rate-limited IP: " . ($this->getClientIp()));
            $this->respondLoginError('ip_locked', "Too many failed login attempts from this network. Please wait $wait minutes.", ['wait' => $wait], 429);
        }

        // 3b. Account-specific throttle check (IP + username keyed)
        $waitSeconds = $this->getThrottleWaitSeconds($identity);
        if ($waitSeconds > 0) {
            $wait = (int) ceil($waitSeconds / 60);
            $this->respondLoginError('locked', "Account temporarily locked due to multiple failed attempts. Please wait $wait minutes.", ['wait' => $wait], 429);
        }

        // 4. DB lookup — both fields fetched always to prevent timing attacks
        $select = 'SELECT id, user, password, role, status, full_name, chapel_id';
        if ($this->supportsLoginLocking()) {
            $select .= ', login_attempts, locked_until';
        }
        $select .= ' FROM users WHERE LOWER(TRIM(user)) = LOWER(?) OR LOWER(TRIM(COALESCE(email, \'\'))) = LOWER(?) LIMIT 1';

        $stmt = $this->conn->prepare($select);
        if (!$stmt) {
            throw new \RuntimeException('Login query preparation failed: ' . $this->conn->error);
        }
        $stmt->bind_param('ss', $identity, $identity);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $stmt->close();

        // 5. DB-level lock check
        if ($user && $this->supportsLoginLocking()
            && !empty($user['locked_until'])
            && strtotime($user['locked_until']) > time()
        ) {
            $wait = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            // Still log the attempt before redirecting
            $this->auditLog('failed_login', "Account locked — login attempt for '{$identity}'");
            $this->respondLoginError('locked', "Account temporarily locked. Please wait $wait minutes.", ['wait' => $wait], 429);
        }

        // 6. Constant-time password verification
        //    We always call password_verify even when user not found (dummy hash)
        //    to prevent timing-based user enumeration.
        $storedPassword = $user ? (string)($user['password'] ?? '') : '';
        $dummyHash      = '$2y$12$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'; // always fails
        $hashToVerify   = $storedPassword !== '' ? $storedPassword : $dummyHash;

        $passwordInfo   = password_get_info($hashToVerify);
        $isCurrentHash  = !empty($passwordInfo['algo']);

        $passwordOk = $isCurrentHash
            ? password_verify($password, $hashToVerify)
            : $this->verifyLegacyPassword($password, $hashToVerify);

        // 7. Evaluate outcome & enforce Super Admin login gateway separation
        $isSuperAdminAccount = ($user && strcasecmp(trim((string)($user['role'] ?? '')), 'Admin') === 0);

        // 7b. Block non-Super-Admin accounts from logging in through the Super Admin direct link
        if ($isSuperAdminChannel && $user && !$isSuperAdminAccount) {
            $this->recordThrottleFailure($identity);
            $this->recordIpThrottleFailure();
            $this->auditLog('unauthorized_admin_portal', "Blocked non-Super-Admin '{$user['user']}' (role: {$user['role']}) on Super Admin login gateway");
            $this->respondLoginError('wrong_pass', 'Invalid Super Administrator credentials.');
        }

        if ($user && $passwordOk) {
            $userStatus = strtolower(trim((string)($user['status'] ?? 'active')));
            if ($userStatus === 'pending') {
                $this->auditLog('pending_login', "Member login blocked (Pending Approval) for '{$identity}'");
                if ($this->isAjaxRequest()) {
                    header('Content-Type: application/json; charset=utf-8');
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error'   => 'Your account registration is awaiting verification by the Cathedral Parish Office.',
                        'notice'  => 'pending_approval'
                    ]);
                    exit();
                }
                $this->redirect('../../index.php?notice=pending_approval');
            } elseif ($userStatus === 'rejected') {
                $this->auditLog('rejected_login', "Member login blocked (Registration Declined) for '{$identity}'");
                $this->respondLoginError('account_rejected', 'Your registration could not be verified by the Cathedral Administration.', [], 403);
            } else {
                $this->handleSuccessfulLogin($user, $password, $isCurrentHash, $identity);
            }
        } else {
            $this->handleFailedLogin($user, $identity);
        }
    }

    // ─── SUCCESSFUL LOGIN ─────────────────────────────────────────────────────
    private function handleSuccessfulLogin(array $user, string $password, bool $isCurrentHash, string $identity): void {
        // Reset DB lock counter
        $this->resetLoginAttempts((int) $user['id']);
        $this->clearThrottleState($identity);

        // Session fixation prevention
        session_regenerate_id(true);

        // Store session fingerprint (IP + UA binding)
        $_SESSION['_fp'] = $this->makeFingerprint();

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['user'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'] ?: $user['user'];
        $_SESSION['chapel_id'] = $user['chapel_id'];
        $_SESSION['login_at']  = time();

        $this->auditLog('login', "User '{$user['user']}' signed in");

        // Migrate plain-text OR upgrade underpowered bcrypt cost
        $needsRehash = !$isCurrentHash
            || password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        if ($needsRehash) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
            $upd = $this->conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('si', $newHash, $user['id']);
                $upd->execute();
                $upd->close();
            }
        }

        $allowedRedirectSections = ['request_event', 'request_certificate', 'events', 'bulletin', 'merchandise', 'public_inquiries'];
        $reqRedirect = trim((string)($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
        $destSection = in_array($reqRedirect, $allowedRedirectSections, true) ? $reqRedirect : '';
        if (in_array($destSection, ['request_event', 'request_certificate'], true) && in_array($user['role'] ?? '', ['Priest', 'Pastor'], true)) {
            $destSection = '';
        }
        $ajaxRedirect = $destSection !== '' ? ('dashboard.php?section=' . urlencode($destSection)) : 'dashboard.php';
        $fullRedirect = $destSection !== '' ? ('../../dashboard.php?section=' . urlencode($destSection)) : '../../dashboard.php';

        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'  => true,
                'redirect' => $ajaxRedirect
            ]);
            exit();
        }

        $this->redirect($fullRedirect);
    }

    // ─── FAILED LOGIN ─────────────────────────────────────────────────────────
    private function handleFailedLogin(?array $user, string $identity): void {
        if ($user) {
            $this->recordFailedLogin((int) $user['id'], (int) ($user['login_attempts'] ?? 0));
        }
        $this->recordThrottleFailure($identity);
        $this->recordIpThrottleFailure();

        // Check if throttle threshold was just reached on this attempt
        $waitSeconds = $this->getThrottleWaitSeconds($identity);
        if ($waitSeconds > 0) {
            $wait = (int) ceil($waitSeconds / 60);
            $this->auditLog('account_throttle_lock', "Account '{$identity}' locked for {$wait}m due to threshold exceeded");
            $this->respondLoginError('locked', "Account temporarily locked due to multiple failed attempts. Please wait {$wait} minutes.", ['wait' => $wait, 'wait_seconds' => $waitSeconds], 429);
        }

        $ipWaitSeconds = $this->getIpThrottleWaitSeconds();
        if ($ipWaitSeconds > 0) {
            $wait = (int) ceil($ipWaitSeconds / 60);
            $this->auditLog('ip_throttle_lock', "IP rate-limited for {$wait}m");
            $this->respondLoginError('ip_locked', "Too many failed login attempts from this network. Please wait {$wait} minutes.", ['wait' => $wait, 'wait_seconds' => $ipWaitSeconds], 429);
        }

        // Generic error — never reveal whether username exists (prevents enumeration)
        $this->auditLog('failed_login', "Failed login attempt for '{$identity}'");
        $this->respondLoginError('wrong_pass', 'Invalid username or password. Please verify credentials.');
    }

    // ─── LOGOUT ───────────────────────────────────────────────────────────────
    public function logout(string $redirectUrl = 'index.php'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (isset($_SESSION['user_id'])) {
            $this->auditLog('logout', "User '{$_SESSION['username']}' signed out");
        }

        // Full session destruction
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->redirect($redirectUrl);
    }

    // ─── SESSION FINGERPRINT ─────────────────────────────────────────────────
    /**
     * Resolves the real client IP across reverse proxies, Cloudflare, and load balancers.
     */
    public static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $raw = trim($_SERVER[$h]);
                if (strpos($raw, ',') !== false) {
                    $parts = explode(',', $raw);
                    $raw = trim($parts[0]);
                }
                if (filter_var($raw, FILTER_VALIDATE_IP)) {
                    return $raw;
                }
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    /**
     * Returns a hash binding the session to this browser's UA and IP subnet.
     * Binding to class B subnet (/16) avoids killing sessions when reverse proxies or
     * mobile cellular connections rotate edge IPs within the same network.
     */
    public static function makeFingerprint(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = self::getClientIp();
        $ipPrefix = '';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $ipPrefix = $parts[0] . '.' . $parts[1];
        }
        return hash('sha256', $ua . '|' . $ipPrefix);
    }

    // ─── LEGACY PASSWORD SUPPORT ─────────────────────────────────────────────
    private function verifyLegacyPassword(string $password, string $stored): bool {
        if (preg_match('/^[a-f0-9]{32}$/i', $stored)) {
            return hash_equals(strtolower($stored), md5($password));
        }
        if (preg_match('/^[a-f0-9]{40}$/i', $stored)) {
            return hash_equals(strtolower($stored), sha1($password));
        }
        return false;
    }

    // ─── AUDIT LOG HELPER ─────────────────────────────────────────────────────
    private function auditLog(string $action, string $detail): void {
        try {
            require_once __DIR__ . '/../models/AuditLogger.php';
            AuditLogger::logFromSession($this->conn, $action, 'Auth', $detail);
        } catch (\Throwable $e) {
            // Non-fatal — never let audit failure break auth flow
        }
    }

    // ─── DB LOCK MANAGEMENT ──────────────────────────────────────────────────
    private function supportsLoginLocking(): bool {
        if ($this->supportsLoginLocking !== null) return $this->supportsLoginLocking;
        $required = $this->inspectLockColumns();
        if (in_array(false, $required, true)) {
            $this->tryProvisionLockColumns($required);
            $required = $this->inspectLockColumns();
        }
        $this->supportsLoginLocking = !in_array(false, $required, true);
        return $this->supportsLoginLocking;
    }

    private function inspectLockColumns(): array {
        $required = ['login_attempts' => false, 'locked_until' => false];
        $result   = $this->conn->query('SHOW COLUMNS FROM users');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $f = $row['Field'] ?? '';
                if (array_key_exists($f, $required)) $required[$f] = true;
            }
            $result->close();
        }
        return $required;
    }

    private function tryProvisionLockColumns(array $required): void {
        $clauses = [];
        if (empty($required['login_attempts'])) $clauses[] = 'ADD COLUMN login_attempts INT UNSIGNED NOT NULL DEFAULT 0';
        if (empty($required['locked_until']))   $clauses[] = 'ADD COLUMN locked_until DATETIME NULL DEFAULT NULL';
        if (!empty($clauses)) @$this->conn->query('ALTER TABLE users ' . implode(', ', $clauses));
    }

    private function resetLoginAttempts(int $userId): void {
        if (!$this->supportsLoginLocking()) return;
        $stmt = $this->conn->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?');
        if ($stmt) { $stmt->bind_param('i', $userId); $stmt->execute(); $stmt->close(); }
    }

    private function recordFailedLogin(int $userId, int $currentAttempts): void {
        if (!$this->supportsLoginLocking()) return;
        $newAttempts = $currentAttempts + 1;
        $lockTime    = $newAttempts >= self::THROTTLE_MAX_ATTEMPTS
            ? date('Y-m-d H:i:s', strtotime('+15 minutes'))
            : null;
        $stmt = $this->conn->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?');
        if ($stmt) { $stmt->bind_param('isi', $newAttempts, $lockTime, $userId); $stmt->execute(); $stmt->close(); }
    }

    // ─── FILE-BASED THROTTLE (IP + username keyed) ───────────────────────────

    private function getThrottleWaitSeconds(string $username): int {
        $attempts = $this->loadThrottleAttempts($username);
        if (count($attempts) < self::THROTTLE_MAX_ATTEMPTS) return 0;
        $oldest = $attempts[count($attempts) - self::THROTTLE_MAX_ATTEMPTS] ?? null;
        if ($oldest === null) return 0;
        return max(0, (int)(($oldest + self::THROTTLE_WINDOW_SECONDS) - time()));
    }

    private function getIpThrottleWaitSeconds(): int {
        $attempts = $this->loadIpThrottleAttempts();
        if (count($attempts) < self::IP_THROTTLE_MAX_ATTEMPTS) return 0;
        $oldest = $attempts[count($attempts) - self::IP_THROTTLE_MAX_ATTEMPTS] ?? null;
        if ($oldest === null) return 0;
        return max(0, (int)(($oldest + self::IP_THROTTLE_WINDOW_SECONDS) - time()));
    }

    private function recordThrottleFailure(string $username): void {
        $attempts   = $this->loadThrottleAttempts($username);
        $attempts[] = time();
        $this->saveThrottleAttempts($username, $attempts);
    }

    private function recordIpThrottleFailure(): void {
        $attempts   = $this->loadIpThrottleAttempts();
        $attempts[] = time();
        $this->saveIpThrottleAttempts($attempts);
    }

    private function clearThrottleState(string $username): void {
        $path = $this->getThrottleFilePath($username);
        if (is_file($path)) @unlink($path);
    }

    private function loadThrottleAttempts(string $username): array {
        $path = $this->getThrottleFilePath($username);
        if (!is_file($path)) return [];
        $raw     = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded)) return [];
        $cutoff  = time() - self::THROTTLE_WINDOW_SECONDS;
        $fresh   = array_values(array_filter($decoded, fn($t) => is_int($t) && $t >= $cutoff));
        if (count($fresh) !== count($decoded)) $this->saveThrottleAttempts($username, $fresh);
        return $fresh;
    }

    private function loadIpThrottleAttempts(): array {
        $path = $this->getIpThrottleFilePath();
        if (!is_file($path)) return [];
        $raw     = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded)) return [];
        $cutoff  = time() - self::IP_THROTTLE_WINDOW_SECONDS;
        $fresh   = array_values(array_filter($decoded, fn($t) => is_int($t) && $t >= $cutoff));
        if (count($fresh) !== count($decoded)) $this->saveIpThrottleAttempts($fresh);
        return $fresh;
    }

    private function saveThrottleAttempts(string $username, array $attempts): void {
        if ($username === '') return;
        $dir = BASE_PATH . '/tmp/auth_throttle';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($this->getThrottleFilePath($username), json_encode(array_values($attempts)), LOCK_EX);
    }

    private function saveIpThrottleAttempts(array $attempts): void {
        $dir = BASE_PATH . '/tmp/auth_throttle';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($this->getIpThrottleFilePath(), json_encode(array_values($attempts)), LOCK_EX);
    }

    private function getThrottleFilePath(string $username): string {
        $ip  = $this->getClientIp();
        $key = strtolower(trim($username)) . '|' . $ip;
        return BASE_PATH . '/tmp/auth_throttle/' . sha1($key) . '.json';
    }

    private function getIpThrottleFilePath(): string {
        $ip  = $this->getClientIp();
        $key = 'ip_only|' . $ip;
        return BASE_PATH . '/tmp/auth_throttle/' . sha1($key) . '.json';
    }

    protected function redirect($url) {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json');
            if (str_contains($url, 'dashboard.php')) {
                echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
                exit();
            }

            // Extract error message if redirected to login.php?error=...
            $parsed = parse_url($url);
            $query = [];
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $query);
            }

            $errMsg = 'Invalid username or password. Please verify your credentials.';
            if (isset($query['error'])) {
                $err = $query['error'];
                if ($err === 'session') $errMsg = "Your session has expired. Please sign in again.";
                else if ($err === 'wrong_pass') $errMsg = "Invalid username or password. Please verify your credentials.";
                else if ($err === 'csrf') $errMsg = "Security validation failed. Please refresh the page.";
                else if ($err === 'empty_all') $errMsg = "Please enter both username and password.";
                else if ($err === 'empty_user') $errMsg = "Username or email address is required.";
                else if ($err === 'empty_pass') $errMsg = "Password is required.";
                else if ($err === 'user_not_found') $errMsg = "Account not found. Please check your credentials.";
                else if ($err === 'account_rejected') $errMsg = "Your registration could not be verified by the Cathedral Administration.";
                else if ($err === 'locked') {
                    $wait = htmlspecialchars($query['wait'] ?? '15');
                    $errMsg = "Account temporarily locked due to multiple failed attempts. Please wait {$wait} minutes.";
                }
            } else if (isset($query['notice']) && $query['notice'] === 'pending_approval') {
                $errMsg = "Your account registration has been submitted and is currently awaiting verification by the Cathedral Parish Office.";
            }

            echo json_encode(['success' => false, 'error' => $errMsg, 'url' => $url]);
            exit();
        }

        parent::redirect($url);
    }
}

