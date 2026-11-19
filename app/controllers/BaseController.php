<?php
/**
 * BaseController — Shared core logic for all features.
 * Enforces structured RBAC and chapel-based data isolation.
 */
class BaseController {
    protected $conn;
    protected $userId;
    protected $role;
    protected $chapelId;
    protected $permissions = [];
    
    public function getRole() { return $this->role; }
    public function getUserId() { return $this->userId; }
    public function getChapelId() { return $this->chapelId; }
    public function getPermissions() { return $this->permissions; }

    public function __construct($conn, $must_auth = true) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $this->conn = $conn;
        
        if ($must_auth) {
            $this->checkSession();
        }
    }

    protected function isAjaxRequest(): bool {
        return (isset($_GET['ajax']) && $_GET['ajax'] == '1')
            || (isset($_POST['ajax']) && $_POST['ajax'] == '1')
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    }

    protected function respondSessionExpired(): void {
        if ($this->isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'session_expired' => true,
                'error' => 'Your session has expired for security. Please log in again.',
                'redirect' => 'index.php?error=session'
            ]);
            exit();
        }
        $target = (strpos($_SERVER['PHP_SELF'] ?? '', '/views/pages/') !== false) ? '../../index.php?error=session' : 'index.php?error=session';
        header("Location: $target");
        exit();
    }

    protected function checkSession() {
        if (!isset($_SESSION['user_id'])) {
            $this->respondSessionExpired();
        }

        // Validate session fingerprint (UA + IP subnet binding)
        if (isset($_SESSION['_fp'])) {
            require_once __DIR__ . '/AuthController.php';
            $currentFp = AuthController::makeFingerprint();
            if (!hash_equals($_SESSION['_fp'], $currentFp)) {
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
                $this->respondSessionExpired();
            }
        }

        $this->userId = $_SESSION['user_id'];
        $this->role   = $_SESSION['role'] ?? 'Staff';
        $this->chapelId = $_SESSION['chapel_id'] ?? null;
        $this->initPermissions();
    }

    public function generateCsrf() {
        if (!isset($_SESSION['csrf']) || empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public function validateCsrf($token = null) {
        $submitted = $token ?? ($_POST['csrf_token'] ?? ($_POST['csrf'] ?? ''));
        $actual    = $_SESSION['csrf'] ?? '';
        return (!empty($actual) && hash_equals($actual, $submitted));
    }

    protected function validateCsrfOrDie() {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Security session expired. Please refresh the page.');
            $this->redirect('dashboard.php');
        }
    }

    protected function enforceCsrf($redirectUrl = 'dashboard.php', $message = 'Security session expired. Please refresh.') {
        if (!$this->validateCsrf()) {
            $this->flash('error', $message);
            $this->redirect($redirectUrl);
        }
    }

    /**
     * Role-Based Access Control and Chapel-Based Isolation Check.
     * Centralized function to validate if a user can perform an action on a resource.
     */
    public function checkPermission($action, $resource, $recordChapelId = null, $createdBy = null) {
        // Super Admin (Admin) has global executive monitoring & audit capabilities across everything
        if ($this->role === 'Admin') {
            return true;
        }

        // Technical database backups are strictly reserved for Super Admin
        if ($resource === 'backups') {
            return false;
        }

        // User management is strictly reserved for Super Admin and Cathedral Admin
        if ($resource === 'users' && !in_array($this->role, ['Admin', 'Cathedral Admin'], true)) {
            return false;
        }

        // Cathedral Admin: Operational approver for Mother Cathedral
        if ($this->role === 'Cathedral Admin') {
            return true;
        }

        // Enforce Chapel Isolation: Users can ONLY access records from their own chapel
        if ($recordChapelId !== null && (int)$this->chapelId !== (int)$recordChapelId) {
            return false;
        }

        // Role-based logic
        switch ($this->role) {
            case 'Staff':
                // Sensitive governance resources blocked from staff
                if (in_array($resource, ['backups', 'users', 'settings', 'reports', 'insights', 'audit_log', 'schedule_audit'], true)) {
                    return false;
                }
                if ($action === 'delete') return false; // Maker-Checker: Staff cannot delete critical records
                if ($action === 'approve') return false; // Maker-Checker: Staff cannot self-approve records or events
                if (in_array($action, ['create', 'view'])) return true;
                if ($action === 'update') {
                    // Staff can update records within their assigned chapel branch (or cathedral if assigned to cathedral)
                    return $this->checkChapelAccess($recordChapelId);
                }
                break;

            case 'Member':
                if ($action === 'view' && in_array($resource, ['events', 'calendar', 'schedule', 'bulletin', 'services', 'merchandise', 'request_certificate', 'request_event', 'public_inquiries', 'my_profile'], true)) {
                    return true;
                }
                if ($action === 'create' && in_array($resource, ['request_certificate', 'request_event', 'public_inquiries'], true)) {
                    return true;
                }
                return false;

            case 'Guest':
                if ($action === 'view' && in_array($resource, ['events', 'calendar', 'schedule', 'bulletin', 'services', 'parish_info'], true)) {
                    return true;
                }
                return false;

            case 'Chapel Admin':
                if (in_array($resource, ['backups', 'users'], true)) {
                    return false;
                }
                // Full visibility and control strictly within their chapel
                if (in_array($action, ['create', 'view', 'update', 'delete', 'approve', 'reject', 'override'])) return true;
                break;

            case 'Priest':
            case 'Pastor':
                if (in_array($resource, ['events', 'calendar', 'schedule', 'priest_schedule', 'pastor_schedule', 'priest_prefs', 'pastor_prefs', 'bulletin'], true)) {
                    if (in_array($action, ['view', 'update_assigned', 'post_bulletin'])) return true;
                }
                return false;
        }

        return $this->permissions[$resource][$action] ?? false;
    }

    /**
     * Helper to check if user has access to a specific chapel's data.
     */
    public function checkChapelAccess($recordChapelId) {
        if (in_array($this->role, ['Admin', 'Cathedral Admin'], true)) {
            return true;
        }
        // NULL or 0 chapel_id = Main Cathedral (global) — only cathedral staff/admin have access
        if ($recordChapelId === null || $recordChapelId === '' || $recordChapelId === '0' || (int)$recordChapelId === 0) {
            return $this->chapelId === null || (int)$this->chapelId === 0;
        }
        // User must be assigned to this chapel
        return $this->chapelId !== null && (int)$this->chapelId === (int)$recordChapelId;
    }

    protected function enforceChapelAccess($recordChapelId, $redirectUrl = 'dashboard.php', $message = 'Unauthorized access to chapel data.') {
        if (!$this->checkChapelAccess($recordChapelId)) {
            $this->flash('error', $message);
            $this->redirect($redirectUrl);
        }
    }

    /**
     * Helper to get the chapel ID that should be applied to a record.
     * Non-admins are forced to use their assigned chapel ID.
     */
    public function getAppliedChapelId($requestedId) {
        if (in_array($this->role, ['Admin', 'Cathedral Admin'])) {
            return $requestedId ? (int)$requestedId : null;
        }
        // Return raw ID (null stays null, strings are fine for MySQL)
        return $this->chapelId;
    }

    protected function initPermissions() {
        $r = $this->role;
        $isSuperAdmin  = ($r === 'Admin');
        $isCathAdmin   = ($r === 'Cathedral Admin');
        $isAdmin       = ($isSuperAdmin || $isCathAdmin);
        $isChapelAdmin = ($r === 'Chapel Admin');
        $isStaff       = ($r === 'Staff');
        $isPriest      = ($r === 'Priest' || $r === 'Pastor');
        $isMember      = ($r === 'Member');
        $isGuest       = ($r === 'Guest');

        $this->permissions = [
            // Executive Monitoring & System Governance
            'can_manage_users'     => $isAdmin,
            'can_backups'          => $isSuperAdmin, // Super Admin executive monitor only
            'can_manage_chapels'   => $isAdmin,
            'can_audit_log'        => ($isAdmin || $isChapelAdmin),
            'can_audit_health'     => ($isAdmin || $isChapelAdmin),

            // Sacramental Records & Registry
            'can_cathedral_rec'    => ($isAdmin || ($isStaff && ($this->chapelId === null || (int)$this->chapelId === 0))),
            'can_chapel_rec'       => ($isAdmin || $isChapelAdmin || $isStaff),
            'can_create_records'   => ($isAdmin || $isChapelAdmin || $isStaff),
            'can_approve_records'  => ($isAdmin || $isChapelAdmin), // Maker-Checker: Staff encodes, Admin approves
            'can_delete_records'   => ($isAdmin || $isChapelAdmin), // Maker-Checker: Staff cannot delete records

            // Liturgical Schedules & Calendar
            'can_view_sched'           => true,
            'can_view_full_calendar'   => true,
            'can_view_public_schedule' => true,
            'can_cathedral_sched'      => $isAdmin,
            'can_chapel_sched'         => ($isAdmin || $isChapelAdmin || $isStaff),
            'can_approve_events'       => ($isAdmin || $isChapelAdmin), // Maker-Checker: Staff cannot self-approve bookings
            'can_update_events'        => ($isAdmin || $isChapelAdmin),
            'can_delete_events'        => ($isAdmin || $isChapelAdmin),
            'can_request_event'        => !$isGuest,

            // Clergy Pastoral Duty
            'can_pastor_prefs'     => ($isPriest || $isAdmin),
            'can_priest_prefs'     => ($isPriest || $isAdmin),
            'can_pastor_dss'       => ($isPriest || $isAdmin),
            'can_priest_dss'       => ($isPriest || $isAdmin),

            // Stewardship & Finances
            'can_donations'        => ($isAdmin || $isChapelAdmin || $isStaff), // Staff enters offerings & issues receipts
            'can_manage_donations' => $isAdmin,
            'can_finance_reports'  => $isAdmin,
            'can_reports'          => ($isAdmin || $isChapelAdmin), // Staff cannot view executive financial reports
            'can_insights'         => ($isAdmin || $isChapelAdmin),

            // Parish Community & Desk
            'can_bulletin'         => true,
            'can_post_bulletin'    => ($isAdmin || $isChapelAdmin || $isPriest),
            'can_manage_inquiries' => ($isAdmin || $isChapelAdmin || $isStaff),
            'parish_info'          => $isAdmin,
            'can_parish_info'      => $isAdmin,
            'can_manage_services'  => $isAdmin,
            'can_view_services'    => true,
            'can_tickets'          => ($isAdmin || $isChapelAdmin || $isStaff)
        ];
    }

    protected function flash($type, $msg) {
        $_SESSION['flash'] = $msg;
        $_SESSION['flash_type'] = $type;
    }

    protected function redirect($url) {
        header("Location: " . $url);
        exit();
    }
}
