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

    // Self-service Forgot Password constraints (root 'Admin' stays excluded for security)
    private const PASSWORD_RESET_ALLOWED_ROLES = ['Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest', 'Member'];
    private const PASSWORD_RESET_TTL_SECONDS   = 900;
    private const PASSWORD_RESET_IP_MAX        = 5;
    private const PASSWORD_RESET_ID_COOLDOWN   = 120;
    private const PASSWORD_RESET_ID_MAX        = 3;
    private const PASSWORD_RESET_VERIFY_MAX    = 10;

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
        if ($this->supportsMustChangePassword()) {
            $select .= ', must_change_password';
        }
        $select .= ' FROM users WHERE user = ? OR email = ? LIMIT 1';

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

        if (!empty($user['must_change_password'])) {
            $_SESSION['must_change_password'] = 1;
            $ajaxRedirect = 'dashboard.php?section=my_profile&notice=must_change_password';
            $fullRedirect = '../../dashboard.php?section=my_profile&notice=must_change_password';
        } else {
            unset($_SESSION['must_change_password']);
        }

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
     * Resolves client IP safely. Only trusts proxy forwarding headers when explicitly
     * opted in via TRUST_PROXY_HEADERS === true.
     */
    public static function getClientIp(): string {
        $trustProxies = defined('TRUST_PROXY_HEADERS') && TRUST_PROXY_HEADERS === true;
        if ($trustProxies) {
            $headers = [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
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

    private ?bool $supportsMustChangeFlag = null;

    public function supportsMustChangePassword(): bool {
        if ($this->supportsMustChangeFlag !== null) return $this->supportsMustChangeFlag;
        $hasCol = false;
        $res = @$this->conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
        if ($res) {
            $hasCol = $res->num_rows > 0;
            $res->close();
        }
        if (!$hasCol) {
            @$this->conn->query('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0');
            $res2 = @$this->conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
            if ($res2) {
                $hasCol = $res2->num_rows > 0;
                $res2->close();
            }
        }
        $this->supportsMustChangeFlag = $hasCol;
        return $this->supportsMustChangeFlag;
    }

    // ─── SELF-SERVICE FORGOT & RESET PASSWORD ────────────────────────────────

    private function ensurePasswordResetTable(): bool {
        $sql = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            requested_ip VARCHAR(45) NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pr_token_hash (token_hash),
            INDEX idx_pr_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        return (bool)@$this->conn->query($sql);
    }

    private function getTrustedAppBaseUrl(): string {
        global $localConfig;
        $envUrl = trim((string)(getenv('APP_URL') ?: ($localConfig['app_url'] ?? '')));
        if ($envUrl !== '' && preg_match('#^https?://[a-z0-9.\-]+(:\d{1,5})?(/[^\s]*)?$#i', $envUrl)) {
            return rtrim($envUrl, '/');
        }
        if (defined('BASE_URL')) {
            $parsed = @parse_url((string)BASE_URL);
            $scheme = strtolower((string)($parsed['scheme'] ?? 'http'));
            $host   = (string)($parsed['host'] ?? '');
            $port   = isset($parsed['port']) ? ':' . (int)$parsed['port'] : '';
            $path   = rtrim((string)($parsed['path'] ?? ''), '/');
            if (in_array($scheme, ['http', 'https'], true) && preg_match('/^[a-z0-9.\-]+$/i', $host)) {
                return $scheme . '://' . $host . $port . $path;
            }
        }
        return 'http://localhost/IFILOCAL';
    }

    private function respondResetRequest(bool $success, string $message, int $httpStatus = 200, string $redirectQuery = ''): void {
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($httpStatus);
            echo json_encode(['success' => $success, 'message' => $message]);
            exit();
        }
        $target = 'index.php?open_login=1';
        if ($redirectQuery !== '') {
            $target .= '&' . ltrim($redirectQuery, '&');
        }
        parent::redirect($target);
    }

    /**
     * Handles Forgot Password submission cleanly with CSRF, RateLimiter,
     * constant-response anti-enumeration, role restrictions, and SHA-256 token storage.
     */
    public function requestPasswordReset(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            parent::redirect('index.php?open_login=1');
            return;
        }

        if (!$this->validateCsrf($_POST['csrf'] ?? $_POST['csrf_token'] ?? '')) {
            $this->respondResetRequest(false, 'Security session expired. Please refresh and try again.', 403, 'error=csrf');
            return;
        }
        // Release session lock before external RateLimiter/SMTP I/O so other tabs aren't blocked
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $rawIdentity = trim((string)($_POST['identity'] ?? $_POST['user'] ?? ''));
        if ($rawIdentity === '') {
            $this->respondResetRequest(false, 'Please enter your username or email address.', 422);
            return;
        }
        if (mb_strlen($rawIdentity) > self::MAX_IDENTITY_LEN) {
            $this->respondResetRequest(false, 'Username or email cannot exceed ' . self::MAX_IDENTITY_LEN . ' characters.', 422);
            return;
        }

        require_once __DIR__ . '/../helpers/RateLimiter.php';
        $ip = self::getClientIp();
        $ipKey = 'pwreset_ip:' . $ip;
        $idCoolKey = 'pwreset_cd:' . sha1(strtolower($rawIdentity));
        $idMaxKey  = 'pwreset_id:' . sha1(strtolower($rawIdentity));

        if (
            RateLimiter::tooManyAttempts($ipKey, self::PASSWORD_RESET_IP_MAX, self::PASSWORD_RESET_TTL_SECONDS)
            || RateLimiter::tooManyAttempts($idCoolKey, 1, self::PASSWORD_RESET_ID_COOLDOWN)
            || RateLimiter::tooManyAttempts($idMaxKey, self::PASSWORD_RESET_ID_MAX, self::PASSWORD_RESET_TTL_SECONDS)
        ) {
            $this->respondResetRequest(false, 'Too many password reset requests. Please wait a few minutes before trying again.', 429);
            return;
        }

        RateLimiter::hit($ipKey, self::PASSWORD_RESET_TTL_SECONDS);
        RateLimiter::hit($idCoolKey, self::PASSWORD_RESET_ID_COOLDOWN);
        RateLimiter::hit($idMaxKey, self::PASSWORD_RESET_TTL_SECONDS);

        $genericMsg = 'If an active eligible account matches that username or email, password reset instructions have been sent to its registered email address.';

        if (!$this->ensurePasswordResetTable()) {
            $this->auditLog('password_reset_error', 'Failed to provision password_resets table.');
            $this->respondResetRequest(true, $genericMsg, 200, 'notice=password_reset_sent');
            return;
        }

        $stmt = $this->conn->prepare('SELECT id, user, full_name, email, role, status, chapel_id FROM users WHERE user = ? OR email = ? LIMIT 1');
        $userRow = null;
        if ($stmt) {
            $stmt->bind_param('ss', $rawIdentity, $rawIdentity);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows === 1) {
                $userRow = $res->fetch_assoc();
            }
            $stmt->close();
        }

        if ($userRow) {
            $role = trim((string)($userRow['role'] ?? ''));
            $status = strtolower(trim((string)($userRow['status'] ?? 'active')));
            $email = trim((string)($userRow['email'] ?? ''));

            if (strcasecmp($role, 'Admin') === 0) {
                $this->auditLog('password_reset_blocked_admin', "Self-service reset blocked for Super Admin '{$userRow['user']}' from {$ip}");
            } elseif ($status === 'active' && in_array($role, self::PASSWORD_RESET_ALLOWED_ROLES, true) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $uid = (int)$userRow['id'];
                $nowStr = date('Y-m-d H:i:s');
                // Single active reset token per user at a time
                $inv = $this->conn->prepare('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL');
                if ($inv) {
                    $inv->bind_param('si', $nowStr, $uid);
                    $inv->execute();
                    $inv->close();
                }

                $rawToken  = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + self::PASSWORD_RESET_TTL_SECONDS);

                $ins = $this->conn->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)');
                if ($ins) {
                    $ins->bind_param('isss', $uid, $tokenHash, $expiresAt, $ip);
                    $okIns = $ins->execute();
                    $ins->close();

                    if ($okIns) {
                        require_once __DIR__ . '/../mail/mailer.php';
                        $displayName = trim((string)($userRow['full_name'] ?: $userRow['user']));
                        $resetLink   = $this->getTrustedAppBaseUrl() . '/reset_password.php?token=' . urlencode($rawToken);
                        $subject     = 'IFI Cathedral — Password Reset Instructions';
                        $body = "<p>Peace be with you, <strong>" . htmlspecialchars($displayName) . "</strong>.</p>"
                              . "<p>A password reset was requested for your IFI Cathedral Portal account (<strong>" . htmlspecialchars($userRow['user']) . "</strong>).</p>"
                              . "<p style=\"margin:20px 0;\"><a href=\"" . htmlspecialchars($resetLink) . "\" style=\"display:inline-block;background:#c5a059;color:#111111;padding:12px 22px;border-radius:8px;font-weight:700;text-decoration:none;\">Reset My Password</a></p>"
                              . "<p style=\"font-size:13px;color:#555;\">Or copy this link into your browser:<br><a href=\"" . htmlspecialchars($resetLink) . "\">" . htmlspecialchars($resetLink) . "</a></p>"
                              . "<p>This one-time link expires in <strong>15 minutes</strong>. If you did not request a password reset, you can safely ignore this email — your password will remain unchanged.</p>";

                        $chapelArg = $userRow['chapel_id'] !== null ? (int)$userRow['chapel_id'] : null;
                        $sent = sendChurchEmail($email, $displayName, $subject, $body, $chapelArg);
                        if ($sent) {
                            $this->auditLog('password_reset_requested', "Reset link sent to '{$userRow['user']}' ({$role}) from {$ip}");
                        } else {
                            $err = $GLOBALS['lastMailError'] ?? 'Unknown mailer error';
                            $this->auditLog('password_reset_mail_failed', "Reset mail failed for '{$userRow['user']}': {$err}");
                        }
                    }
                }
            } else {
                $this->auditLog('password_reset_ineligible', "Reset skipped for '{$userRow['user']}' (status={$status}, role={$role})");
            }
        }

        $this->respondResetRequest(true, $genericMsg, 200, 'notice=password_reset_sent');
    }

    /**
     * Validates a raw 64-char hex reset token against unexpired, unused DB rows and active eligible roles.
     */
    public function verifyResetToken(string $rawToken): ?array {
        $rawToken = trim($rawToken);
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }
        if (!$this->ensurePasswordResetTable()) {
            return null;
        }
        $tokenHash = hash('sha256', $rawToken);
        $sql = 'SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, u.user, u.full_name, u.email, u.role, u.status, u.chapel_id
                  FROM password_resets pr
                  INNER JOIN users u ON u.id = pr.user_id
                 WHERE pr.token_hash = ?
                   AND pr.used_at IS NULL
                 LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return null;
        $expiresTs = strtotime((string)($row['expires_at'] ?? ''));
        if ($expiresTs === false || $expiresTs <= time()) {
            return null;
        }
        $role = trim((string)($row['role'] ?? ''));
        $status = strtolower(trim((string)($row['status'] ?? 'active')));
        if ($status !== 'active' || !in_array($role, self::PASSWORD_RESET_ALLOWED_ROLES, true)) {
            return null;
        }
        return $row;
    }

    /**
     * Completes password reset atomically: verifies CSRF, rate limit, token, password rules,
     * burns token, clears lockouts, writes audit log, and sends confirmation notice.
     */
    public function completePasswordReset(): array {
        if (!$this->validateCsrf($_POST['csrf'] ?? $_POST['csrf_token'] ?? '')) {
            return ['success' => false, 'error' => 'Security session expired. Please refresh the page and try again.', 'code' => 'csrf'];
        }

        require_once __DIR__ . '/../helpers/RateLimiter.php';
        $ip = self::getClientIp();
        $verifyIpKey = 'pwreset_verify_ip:' . $ip;
        if (RateLimiter::tooManyAttempts($verifyIpKey, self::PASSWORD_RESET_VERIFY_MAX, self::PASSWORD_RESET_TTL_SECONDS)) {
            return ['success' => false, 'error' => 'Too many reset attempts. Please wait a few minutes and try again.', 'code' => 'rate_limited'];
        }
        RateLimiter::hit($verifyIpKey, self::PASSWORD_RESET_TTL_SECONDS);

        $rawToken   = trim((string)($_POST['token'] ?? ''));
        $newPwd     = substr(trim((string)($_POST['new_password'] ?? '')), 0, self::MAX_PASSWORD_LEN + 1);
        $confirmPwd = substr(trim((string)($_POST['confirm_password'] ?? '')), 0, self::MAX_PASSWORD_LEN + 1);

        $resetRow = $this->verifyResetToken($rawToken);
        if (!$resetRow) {
            return ['success' => false, 'error' => 'This password reset link is invalid or has expired. Please request a new one.', 'code' => 'invalid_token'];
        }

        $len = mb_strlen($newPwd);
        if ($len < 6 || $len > self::MAX_PASSWORD_LEN) {
            return ['success' => false, 'error' => 'Password must be between 6 and ' . self::MAX_PASSWORD_LEN . ' characters.', 'code' => 'invalid_password'];
        }
        if ($newPwd !== $confirmPwd) {
            return ['success' => false, 'error' => 'New password and confirmation password do not match.', 'code' => 'mismatch'];
        }

        $uid     = (int)$resetRow['user_id'];
        $newHash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        $this->conn->begin_transaction();
        try {
            $sets = ['password = ?'];
            $types = 's';
            $params = [$newHash];
            if ($this->supportsLoginLocking()) {
                $sets[] = 'login_attempts = 0';
                $sets[] = 'locked_until = NULL';
            }
            if ($this->supportsMustChangePassword()) {
                $sets[] = 'must_change_password = 0';
            }
            $types .= 'i';
            $params[] = $uid;

            $updUser = $this->conn->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
            if (!$updUser) throw new \RuntimeException('Failed to prepare password update.');
            $updUser->bind_param($types, ...$params);
            if (!$updUser->execute()) throw new \RuntimeException('Failed to update password.');
            $updUser->close();

            $markUsed = $this->conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            if ($markUsed) {
                $markUsed->bind_param('i', $uid);
                $markUsed->execute();
                $markUsed->close();
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => 'Unable to complete password reset right now. Please try again.', 'code' => 'db_error'];
        }

        // Clear any login throttle left over from locked attempts
        $this->clearThrottleState((string)$resetRow['user']);
        if (!empty($resetRow['email'])) {
            $this->clearThrottleState((string)$resetRow['email']);
        }
        $this->auditLog('password_reset_completed', "Password reset completed for '{$resetRow['user']}' ({$resetRow['role']}) from {$ip}");

        // Security alert email to account owner
        if (!empty($resetRow['email']) && filter_var($resetRow['email'], FILTER_VALIDATE_EMAIL)) {
            require_once __DIR__ . '/../mail/mailer.php';
            $displayName = trim((string)($resetRow['full_name'] ?: $resetRow['user']));
            $alertBody = "<p>Peace be with you, <strong>" . htmlspecialchars($displayName) . "</strong>.</p>"
                       . "<p>Your IFI Cathedral Portal password for <strong>" . htmlspecialchars($resetRow['user']) . "</strong> was successfully changed.</p>"
                       . "<p>If you did not perform this change, please contact the Cathedral Parish Office immediately.</p>";
            $chapelArg = $resetRow['chapel_id'] !== null ? (int)$resetRow['chapel_id'] : null;
            @sendChurchEmail($resetRow['email'], $displayName, 'IFI Cathedral — Security Notice: Password Changed', $alertBody, $chapelArg);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
        }

        return ['success' => true, 'error' => '', 'code' => 'ok'];
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

