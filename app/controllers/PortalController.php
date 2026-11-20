<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Inquiry.php';

/**
 * PortalController — Handles public-facing landing pages and service requests.
 * Eliminates the 'public_requests.php' monolith.
 */
class PortalController extends BaseController {
    private $inquiryModel;

    public function __construct($conn) {
        // Public pages don't require authentication
        parent::__construct($conn, false);
        $this->inquiryModel = new Inquiry($this->conn);
        require_once __DIR__ . '/../models/Chapel.php';
        $this->chapelModel = new Chapel($this->conn);
    }

    /**
     * Entry point for public request processing and rendering.
     */
    public function handlePortalRequest() {
        $flash = $_SESSION['portal_flash'] ?? null;
        $flash_type = $_SESSION['portal_flash_type'] ?? 'success';
        unset($_SESSION['portal_flash'], $_SESSION['portal_flash_type']);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $res = $this->submitRequest();
            $_SESSION['portal_flash'] = $res['message'];
            $_SESSION['portal_flash_type'] = $res['type'];
            
            $allowedTargets = ['public_requests.php', 'portal_request.php', 'request.php', 'index.php'];
            $selfName = basename((string)($_SERVER['PHP_SELF'] ?? 'public_requests.php'));
            $target = in_array($selfName, $allowedTargets, true) ? $selfName : 'public_requests.php';
            header("Location: " . $target);
            exit();
        }

        // Prepare View Data
        $is_logged_in = isset($_SESSION['user_id']);
        $csrf = $this->generateCsrf(); // Ensure CSRF protection even for public forms
        $chapels = $this->chapelModel->getAll();
        
        include BASE_PATH . '/views/pages/portal_request.php';
    }

    public function apiSubmitRequest() {
        return $this->submitRequest();
    }

    private function submitRequest() {
        if (!$this->validateCsrf($_POST['csrf'] ?? null)) {
            return ['type' => 'error', 'message' => 'Security token expired. Please refresh the page and try again.'];
        }

        require_once __DIR__ . '/../helpers/RateLimiter.php';
        $ip = RateLimiter::getClientIp();
        $rateKey = 'portal_submit:' . $ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5, 300)) {
            $waitMins = max(1, (int)ceil(RateLimiter::availableIn($rateKey, 300) / 60));
            return ['type' => 'error', 'message' => "Too many submissions. Please wait $waitMins minute(s) before submitting again."];
        }

        // Spam Prevention: Rate Limiting (60-second session cooldown)
        $lastSubmit = $_SESSION['portal_last_submit'] ?? 0;
        if (time() - $lastSubmit < 60) {
            $remaining = 60 - (time() - $lastSubmit);
            return ['type' => 'error', 'message' => "Too many requests. Please wait {$remaining} seconds before submitting another one."];
        }
        RateLimiter::hit($rateKey, 300);
        
        $action  = $_POST['action'] ?? '';
        $name    = trim($_POST['full_name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $type    = $_POST['request_type'] ?? '';
        $category = $_POST['service_category'] ?? '';
        $target_date = $_POST['target_date'] ?? null;
        $chapel_id   = (int)($_POST['target_chapel'] ?? 0);
        $details = trim($_POST['details'] ?? '');
        $chapel_id = $chapel_id > 0 ? $chapel_id : null;

        // Handle Specialized Merchandise Reservation
        if ($action === 'reserve_merch') {
            $itemId   = $_POST['item_id'] ?? '';
            $itemName = $_POST['item_name'] ?? 'Unknown Item';
            $qty      = (int)($_POST['quantity'] ?? 1);
            if ($qty < 1) $qty = 1;
            if ($qty > 50) $qty = 50; // Human error guard: Max reasonable reservation per request
            
            $type     = 'Merchandise';
            $category = 'Reservation';
            $details  = "Reservation for {$itemName} x {$qty}. (Item ID: {$itemId})";
        }

        // 1. Basic Required Fields
        if (!$name || !$email || !$type || !$details) {
            return ['type' => 'error', 'message' => 'Please fill in all required fields.'];
        }

        // 2. Data Integrity: Email Format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['type' => 'error', 'message' => 'The email address provided is invalid.'];
        }

        // 3. Data Integrity: Type Whitelist
        $allowedTypes = ['Certificate', 'Event Inquiry', 'Merchandise', 'Other'];
        if (!in_array($type, $allowedTypes, true)) {
            return ['type' => 'error', 'message' => 'Invalid request type selected.'];
        }

        // 4. Data Integrity: Length Constraints (Backend Safety)
        if (strlen($name) > 100) $name = substr($name, 0, 100);
        if (strlen($details) > 2000) $details = substr($details, 0, 2000);
        
        // 5. Phone Sanitization
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) > 15) $phone = substr($phone, 0, 15);

        // Spam Prevention: Max Active Pending Requests per Email
        $pendingCount = $this->inquiryModel->getCountByEmailAndStatus($email, 'Pending');
        if ($pendingCount >= 5) {
            return ['type' => 'error', 'message' => 'You already have several pending requests in our queue. Please wait for us to process them before sending more.'];
        }

        // Human Error: Don't allow target dates in the past
        if ($target_date && strtotime($target_date) < strtotime(date('Y-m-d'))) {
            return ['type' => 'error', 'message' => 'The target date cannot be in the past. Please select a future date.'];
        }

        if ($chapel_id !== null && !$this->chapelExists($chapel_id)) {
            return ['type' => 'error', 'message' => 'Please select a valid chapel location.'];
        }

        // Duplicate Check: Prevent exact same request within 5 minutes (accidental double-click/refresh)
        $lastRequest = $_SESSION['portal_last_data'] ?? [];
        if (!empty($lastRequest) && 
            $lastRequest['email'] === $email && 
            $lastRequest['category'] === $category && 
            $lastRequest['date'] === $target_date &&
            (time() - ($lastRequest['time'] ?? 0) < 300)) {
            return ['type' => 'error', 'message' => 'It looks like you just submitted this same request a moment ago. Please check your email for the confirmation.'];
        }

        // Save to DB
        $success = $this->inquiryModel->create($name, $email, $phone, $type, $category, $details, $target_date, $chapel_id);

        if ($success) {
            $_SESSION['portal_last_submit'] = time();
            $_SESSION['portal_last_data'] = [
                'email' => $email,
                'category' => $category,
                'date' => $target_date,
                'time' => time()
            ];
            
            // Automated Acknowledgment
            require_once BASE_PATH . '/app/mail/InquiryMailer.php';
            InquiryMailer::sendAcknowledgment($email, $name, $category);

            return ['type' => 'success', 'message' => 'Your request has been submitted successfully. Our office staff will review it and contact you shortly.'];
        }

        return ['type' => 'error', 'message' => 'Error submitting request. Please try again later.'];
    }

    private function chapelExists(int $chapelId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM chapels WHERE chapel_id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $chapelId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }
}
