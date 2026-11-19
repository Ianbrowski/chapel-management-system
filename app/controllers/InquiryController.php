<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Inquiry.php';
require_once __DIR__ . '/../models/AuditLogger.php';
require_once __DIR__ . '/../mail/InquiryMailer.php';

/**
 * InquiryController — Public service inquiries, messages, and contact requests.
 */
class InquiryController extends BaseController {
    private $inquiryModel;

    public function __construct($conn) {
        parent::__construct($conn);
        $this->inquiryModel = new Inquiry($this->conn);
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        
        $this->enforceCsrf('dashboard.php', 'Security session expired. Please refresh.');
        
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        // Authorization check
        if (!$this->permissions['can_manage_inquiries']) {
            $this->flash('error', 'You do not have permission to manage inquiries.');
            $this->redirect('dashboard.php?section=public_inquiries');
        }

        switch ($action) {
            case 'update':
            case 'update_status': 
            case 'fulfill_merch_reservation':
            case 'delete': 
            case 'archive':
            case 'unarchive':
            case 'send_quick_contact':
            case 'send_quick_sms':
            case 'send_quick_email':
                // OWNERSHIP CHECK - Prevent cross-chapel manipulation
                // Users with no chapel assignment (NULL) can manage all inquiries
                $inquiry = $this->inquiryModel->getById($id);
                $userChapelId = $this->chapelId;
                $canAccess = !$inquiry ? false
                    : (in_array($this->role, ['Admin', 'Cathedral Admin']) 
                       || $userChapelId === null
                       || (int)$userChapelId === (int)$inquiry['chapel_id']
                       || $inquiry['chapel_id'] === null);
                if (!$canAccess) {
                    $this->flash('error', 'Unauthorized: Record does not exist or belongs to another chapel.');
                    $this->redirect('dashboard.php?section=public_inquiries');
                }
                
                switch ($action) {
                    case 'update':
                    case 'update_status':
                        $this->updateStatus($id, $_POST['status'] ?? 'Pending', $_POST['admin_note'] ?? null); 
                        break;
                    case 'fulfill_merch_reservation':
                        $this->fulfillMerchReservation($id);
                        break;
                    case 'delete':
                        $this->delete($id); 
                        break;
                    case 'archive':
                        $this->archive($id);
                        break;
                    case 'unarchive':
                        $this->unarchive($id);
                        break;
                    case 'send_quick_contact':
                        $this->sendQuickContact($id, $_POST['contact_method'] ?? '');
                        break;
                    case 'send_quick_sms':
                        $this->sendQuickContact($id, 'sms');
                        break;
                    case 'send_quick_email':
                        $this->sendQuickContact($id, 'email');
                        break;
                }
                break;
        }
        $this->redirect('dashboard.php?section=public_inquiries');
    }

    private function sendQuickContact($id, $method) {
        $inq = $this->inquiryModel->getById($id);
        if (!$inq) {
            $this->flash('error', 'Inquiry not found.');
            return;
        }

        if ($method === 'email' && !empty($inq['email'])) {
            require_once BASE_PATH . '/app/mail/InquiryMailer.php';
            $sent = InquiryMailer::sendStatusUpdate($inq['email'], $inq['full_name'], $inq['service_category'], 'Pending Review', 'Hello! We are confirming the receipt of your request. We will review it and get back to you shortly.');
            if ($sent) $this->flash('success', 'Auto-Email sent successfully.');
            else $this->flash('error', 'Failed to send Auto-Email.');
        } elseif ($method === 'sms' && !empty($inq['phone'])) {
            require_once BASE_PATH . '/app/services/SmsService.php';
            $cat = $inq['service_category'] ?: 'request';
            $msg = "Hi " . trim($inq['full_name'] ?: 'Parishioner') . "! We have received your {$cat} and our office will review it shortly. Thank you!";
            $sent = SmsService::send($inq['phone'], $msg);
            if ($sent) $this->flash('success', 'Auto-SMS sent successfully.');
            else $this->flash('error', 'Failed to send Auto-SMS.');
        } else {
            $this->flash('error', 'Missing contact information to send ' . strtoupper($method) . '.');
        }
    }

    private function fulfillMerchReservation($id) {
        // 1. Get Details
        $inq = $this->inquiryModel->getById($id);
        if (!$inq) {
            $this->flash('error', 'Inquiry not found.');
            return;
        }

        // 2. Extract Data from Details string
        // Format: "Member Reservation: [Item] x [Qty]. (Item ID: [ID]) ..."
        preg_match('/\(Item ID: (\d+)\)/', $inq['details'], $idMatch);
        preg_match('/x (\d+)\./', $inq['details'], $qtyMatch);

        $itemId = $idMatch ? (int)$idMatch[1] : 0;
        $qty    = $qtyMatch ? (int)$qtyMatch[1] : 1;

        if (!$itemId) {
            $this->flash('error', 'Could not identify the merchandise item.');
            return;
        }

        // 3. Record Sale
        require_once BASE_PATH . '/app/models/Merchandise.php';
        $merchM = new Merchandise($this->conn);
        $res = $merchM->recordSale($itemId, $inq['full_name'], $qty, $this->userId);

        if ($res !== true) {
            $this->flash('error', 'Inventory Error: ' . ($res ?: 'Failed to record sale.'));
            return;
        }

        // 4. Update Inquiry to Completed + auto-archive
        if (!$this->inquiryModel->updateStatus($id, 'Completed', "One-click fulfillment: Automated sale recorded & stock deducted.")) {
            $this->flash('error', 'Could not update inquiry status after recording the sale.');
            return;
        }
        $this->inquiryModel->archive($id);
            // Capture old inquiry state before fulfillment
            $before = $inq;
            // After status update and archiving, retrieve updated record (may be archived)
            $after = $this->inquiryModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Inquiries',
                "Fulfilled Merch Reservation #{$id} automatically.",
                $before,
                $after
            );

        // 5. Trigger Notifications
        $notifSummary = $this->sendInquiryNotifications($id, 'Completed', 'Your reservation has been processed and is now marked as picked up. Thank you!');

        $this->flash('success', "Transaction Auto-Completed! Sale recorded, stock deducted, and parishioner notified. {$notifSummary}");
    }

    private function updateStatus($id, $status, $note = null) {
        $allowedStatuses = ['Pending', 'In Progress', 'Reviewed', 'Completed', 'Cancelled', 'For Claiming'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->flash('error', 'Invalid status value.');
            return;
        }

        $note = trim($note ?? '');
        if ($status === 'For Claiming' && empty($note)) {
            $note = 'Your requested document is now ready for pickup at the Parish Office. Please bring a valid ID and the necessary fees (if any).';
        }

        // Fetch inquiry details for notification
        $inq = $this->inquiryModel->getById($id);
        
        if (!$this->inquiryModel->updateStatus($id, $status, $note)) {
            $this->flash('error', 'Failed to update inquiry.');
            return;
        }
            // Capture old inquiry state before update
            $before = $inq;
            // Perform update (already done above)
            $after = $this->inquiryModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Inquiries',
                "Set inquiry {$id} as {$status}",
                $before,
                $after
            );

        // Auto-archive completed inquiries (Completed/Cancelled)
        // Note: 'For Claiming' stays in inbox because it requires action from parishioner
        if (in_array($status, ['Completed', 'Cancelled'], true)) {
            $this->inquiryModel->archive($id);
        }

        // Handle Notifications
        $notifSummary = $this->sendInquiryNotifications($id, $status, $note);

        $msg = "Inquiry marked as {$status}.";
        if (in_array($status, ['Completed', 'Cancelled'], true)) {
            $msg .= ' Moved to archive.';
        }
        $msg .= " {$notifSummary}";

        $this->flash('success', $msg);
    }

    private function archive($id) {
        if ($this->inquiryModel->archive($id)) {
            // Capture old inquiry state before archiving
            $before = $this->inquiryModel->getById($id);
            $after = $this->inquiryModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Inquiries',
                "Archived inquiry ID: {$id}",
                $before,
                $after
            );
            $this->flash('success', 'Inquiry moved to archive.');
        } else {
            $this->flash('error', 'Failed to archive inquiry.');
        }
    }

    private function delete($id) {
        if ($this->inquiryModel->delete($id)) {
            // Capture old inquiry state before deletion
            $before = $this->inquiryModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'deleted',
                'Inquiries',
                "Deleted inquiry ID: {$id}",
                $before,
                null
            );
            $this->flash('success', 'Inquiry deleted.');
        } else {
            $this->flash('error', 'Failed to delete inquiry.');
        }
    }

    private function sendInquiryNotifications($id, $status, $note) {
        $inq = $this->inquiryModel->getById($id);
        if (!$inq) return '';

        $mailSent = InquiryMailer::sendStatusUpdate($inq['email'], $inq['full_name'], $inq['service_category'], $status, $note);
        
        $smsStatuses = ['Reviewed', 'Completed', 'For Claiming'];
        $smsSent = false;
        if (in_array($status, $smsStatuses, true)) {
            require_once BASE_PATH . '/app/services/SmsService.php';
            $smsSent = SmsService::sendInquiryUpdated($this->conn, $id, $status);
        }

        $notif = [];
        if (!empty($inq['email'])) $notif[] = $mailSent ? 'Email sent' : 'Email failed';
        if (!empty($inq['phone']) && in_array($status, $smsStatuses, true)) $notif[] = $smsSent ? 'SMS sent' : 'SMS failed';
        
        return !empty($notif) ? '(' . implode(', ', $notif) . ')' : '';
    }

    private function unarchive($id) {
        if ($this->inquiryModel->unarchive($id)) {
            // Capture old inquiry state before restoration
            $before = $this->inquiryModel->getById($id);
            $after = $this->inquiryModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Inquiries',
                "Restored inquiry #{$id} from archive.",
                $before,
                $after
            );
            $this->flash('success', 'Request restored to active inbox.');
        } else {
            $this->flash('error', 'Failed to restore inquiry.');
        }
    }

    /**
     * Provides data arrays directly to the Dashboard view.
     */
    public function getInquiryViewData($section) {
        $data = [];
        if ($section === 'public_inquiries') {
            $isMember = ($this->role === 'Member');
            
            if ($isMember) {
                // For members, we fetch based on their registered email
                require_once BASE_PATH . '/app/models/User.php';
                $userM = new User($this->conn);
                $user = $userM->getById($this->userId);
                $email = $user['email'] ?? '';
                
                $page = max(1, (int)($_GET['inq_page'] ?? 1));
                $limit = 30;
                $result = $this->inquiryModel->getByEmail($email, $page, $limit);
                
                $data['inquiries']     = $result['records'];
                $data['inq_total']     = $result['total'];
                $data['inq_pages']     = $result['pages'];
                $data['inq_page']      = $result['page'];
                $data['limit']         = $limit;
                $data['inbox_tab']     = 'my_requests';
                $data['inq_active_count']  = 0; // Not relevant for member view
                $data['inq_archive_count'] = 0;
                $data['inq_filters']       = [];
            } else {
                $chapelFilter = !in_array($this->role, ['Admin', 'Cathedral Admin']) ? $this->chapelId : null;
                $tab    = $_GET['inbox_tab'] ?? 'active';
                $page   = max(1, (int)($_GET['inq_page'] ?? 1));
                $status = $_GET['inquiry_status'] ?? null;
                $sort   = $_GET['inq_sort'] ?? 'date_desc';
                $filters = [
                    'status' => $status,
                    'sort' => $sort,
                    'chapel_id' => $chapelFilter,
                ];

                $limit = 30;
                if ($tab === 'archived') {
                    $result = $this->inquiryModel->getArchived($filters, $page, $limit);
                } else {
                    $result = $this->inquiryModel->getAll($filters, $page, $limit);
                }

                $data['inquiries']     = $result['records'];
                $data['inq_total']     = $result['total'];
                $data['inq_pages']     = $result['pages'];
                $data['inq_page']      = $result['page'];
                $data['limit']         = $limit;
                $data['inq_filters']   = $filters;
                
                $data['inbox_tab']         = $tab;
                $data['inq_active_count']  = $this->inquiryModel->getActiveCount($chapelFilter);
                $data['inq_archive_count'] = $this->inquiryModel->getArchivedCount($chapelFilter);
            }
        }
        return $data;
    }
}
