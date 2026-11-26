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
                $inquiry = $this->inquiryModel->getById($id);
                $userChapelId = $this->chapelId;
                $inqChapelId = $inquiry ? ($inquiry['chapel_id'] ?? null) : null;
                if (!$inquiry) {
                    $canAccess = false;
                } elseif (in_array($this->role, ['Admin', 'Cathedral Admin'], true)) {
                    $canAccess = true;
                } elseif (!empty($userChapelId)) {
                    // Chapel-assigned staff/admin can only manage inquiries for their exact assigned chapel
                    $canAccess = ($inqChapelId !== null && (int)$userChapelId === (int)$inqChapelId);
                } else {
                    // Cathedral Staff (no branch chapel) can manage Cathedral inquiries only
                    $canAccess = ($inqChapelId === null || (int)$inqChapelId === 0);
                }
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

        $customMsg = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 800);
        $cat       = $inq['service_category'] ?: ($inq['request_type'] ?: 'request');
        $firstName = trim($inq['full_name'] ?: 'Parishioner');

        if ($method === 'email' && !empty($inq['email'])) {
            require_once BASE_PATH . '/app/mail/InquiryMailer.php';
            $emailStatus = $customMsg !== '' ? ($inq['status'] ?: 'Parish Office Update') : 'Pending Review';
            $emailBody   = $customMsg !== '' ? $customMsg : 'Hello! We are confirming the receipt of your request. We will review it and get back to you shortly.';
            $sent = InquiryMailer::sendStatusUpdate($inq['email'], $inq['full_name'], $cat, $emailStatus, $emailBody);
            if ($sent) {
                if ($customMsg !== '') {
                    $this->inquiryModel->updateStatus($id, $inq['status'] ?: 'Pending', $customMsg);
                    AuditLogger::logFromSession($this->conn, 'updated', 'Inquiries', "Sent email message for inquiry #{$id}", $inq, array_merge($inq, ['admin_note' => $customMsg]));
                }
                $this->flash('success', 'Email sent to ' . $inq['email'] . '.');
            } else {
                $err = trim((string)($GLOBALS['lastMailError'] ?? ''));
                $this->flash('error', 'Failed to send Email' . ($err !== '' ? ": {$err}" : '.'));
            }
        } elseif ($method === 'sms' && !empty($inq['phone'])) {
            require_once BASE_PATH . '/app/services/SmsService.php';
            $msg = $customMsg !== ''
                ? "Hi {$firstName}! Parish Office: {$customMsg}"
                : "Hi {$firstName}! We have received your {$cat} and our office will review it shortly. Thank you!";
            $sent = SmsService::send($inq['phone'], $msg);
            if ($sent) {
                if ($customMsg !== '') {
                    $this->inquiryModel->updateStatus($id, $inq['status'] ?: 'Pending', $customMsg);
                    AuditLogger::logFromSession($this->conn, 'updated', 'Inquiries', "Sent SMS message for inquiry #{$id}", $inq, array_merge($inq, ['admin_note' => $customMsg]));
                }
                $this->flash('success', 'SMS sent to ' . $inq['phone'] . '.');
            } else {
                $smsErr = trim((string) SmsService::getLastError());
                $this->flash('error', 'Failed to send SMS' . ($smsErr !== '' ? ": {$smsErr}" : '.'));
            }
        } else {
            $this->flash('error', 'Missing contact information to send ' . strtoupper($method) . '.');
        }
    }

    private function fulfillMerchReservation($id) {
        $id = (int)$id;
        require_once BASE_PATH . '/app/models/Merchandise.php';
        $merchM = new Merchandise($this->conn);

        $inq = null;
        $this->conn->begin_transaction();
        try {
            // 1. Lock row & prevent duplicate fulfillment
            $lockStmt = $this->conn->prepare("SELECT * FROM public_inquiries WHERE inquiry_id = ? FOR UPDATE");
            if (!$lockStmt) {
                throw new RuntimeException('Unable to lock reservation record.');
            }
            $lockStmt->bind_param('i', $id);
            $lockStmt->execute();
            $inq = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();

            if (!$inq) {
                throw new RuntimeException('Inquiry not found.');
            }
            if (($inq['status'] ?? '') === 'Completed' || !empty($inq['is_archived'])) {
                throw new RuntimeException('This merchandise reservation has already been fulfilled or archived.');
            }

            // 2. Prefer structured columns (item_id, quantity), fall back to details regex
            $itemId = (int)($inq['item_id'] ?? 0);
            $qty    = (int)($inq['quantity'] ?? 0);
            if ($itemId <= 0 && preg_match('/\(Item ID: (\d+)\)/', (string)($inq['details'] ?? ''), $idMatch)) {
                $itemId = (int)$idMatch[1];
            }
            if ($qty <= 0) {
                $qty = preg_match('/x (\d+)\./', (string)($inq['details'] ?? ''), $qtyMatch) ? (int)$qtyMatch[1] : 1;
            }
            if ($itemId <= 0 || $qty <= 0) {
                throw new RuntimeException('Could not identify the merchandise item or quantity.');
            }

            // 3. Record Sale inside caller transaction
            $res = $merchM->recordSale($itemId, $inq['full_name'], $qty, $this->userId, false);
            if ($res !== true) {
                throw new RuntimeException('Inventory Error: ' . ($res ?: 'Failed to record sale.'));
            }

            // 4. Update Inquiry to Completed + auto-archive atomically
            if (!$this->inquiryModel->updateStatus($id, 'Completed', "One-click fulfillment: Automated sale recorded & stock deducted.")
                || !$this->inquiryModel->archive($id)) {
                throw new RuntimeException('Could not update inquiry status after recording the sale.');
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            $this->flash('error', $e->getMessage());
            return;
        }

        // Capture inquiry state change for audit trail
        $before = $inq;
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
        
        $smsStatuses = ['Reviewed', 'Completed', 'For Claiming', 'Cancelled'];
        $smsSent = false;
        if (in_array($status, $smsStatuses, true)) {
            require_once BASE_PATH . '/app/services/SmsService.php';
            $smsSent = SmsService::sendInquiryUpdated($this->conn, $id, $status);
        }

        $notif = [];
        if (!empty($inq['email'])) {
            $mailErr = trim((string)($GLOBALS['lastMailError'] ?? ''));
            $notif[] = $mailSent ? 'Email sent' : ('Email failed' . ($mailErr !== '' ? ": {$mailErr}" : ''));
        }
        if (!empty($inq['phone']) && in_array($status, $smsStatuses, true)) {
            $smsErr = trim((string) SmsService::getLastError());
            $notif[] = $smsSent ? 'SMS sent' : ('SMS failed' . ($smsErr !== '' ? ": {$smsErr}" : ''));
        }
        
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

                $selectedInqId = (int)($_GET['inq_id'] ?? 0);
                $selectedInq = null;
                if ($selectedInqId > 0) {
                    $cand = $this->inquiryModel->getById($selectedInqId);
                    if ($cand && strcasecmp((string)($cand['email'] ?? ''), (string)$email) === 0) {
                        $selectedInq = $cand;
                    }
                }
                
                $data['inquiries']     = $result['records'];
                $data['inq_total']     = $result['total'];
                $data['inq_pages']     = $result['pages'];
                $data['inq_page']      = $result['page'];
                $data['limit']         = $limit;
                $data['inbox_tab']     = 'my_requests';
                $data['inq_active_count']  = 0; // Not relevant for member view
                $data['inq_archive_count'] = 0;
                $data['inq_filters']       = [];
                $data['selected_inquiry_id'] = $selectedInq ? $selectedInqId : 0;
                $data['selected_inquiry']    = $selectedInq;
            } else {
                if (in_array($this->role, ['Admin', 'Cathedral Admin'], true)) {
                    $chapelFilter = null;
                } elseif (!empty($this->chapelId)) {
                    $chapelFilter = (int)$this->chapelId;
                } else {
                    $chapelFilter = 'cathedral';
                }
                $selectedInqId = (int)($_GET['inq_id'] ?? 0);
                $selectedInq = null;
                if ($selectedInqId > 0) {
                    $cand = $this->inquiryModel->getById($selectedInqId);
                    if ($cand) {
                        $candChapel = (int)($cand['chapel_id'] ?? 0);
                        $canAccess = ($chapelFilter === null)
                            || ($chapelFilter === 'cathedral' && $candChapel === 0)
                            || (is_int($chapelFilter) && $candChapel === $chapelFilter);
                        if ($canAccess) {
                            $selectedInq = $cand;
                        }
                    }
                }
                $tab    = $_GET['inbox_tab'] ?? (($selectedInq && !empty($selectedInq['is_archived'])) ? 'archived' : 'active');
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
                $data['selected_inquiry_id'] = $selectedInq ? $selectedInqId : 0;
                $data['selected_inquiry']    = $selectedInq;
                
                $data['inbox_tab']         = $tab;
                $data['inq_active_count']  = $this->inquiryModel->getActiveCount($chapelFilter);
                $data['inq_archive_count'] = $this->inquiryModel->getArchivedCount($chapelFilter);
            }
        }
        return $data;
    }
}
