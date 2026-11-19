<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/AuditLogger.php';
require_once __DIR__ . '/../services/SmsService.php';

class TicketController extends BaseController {
    private $ticketModel;

    public function __construct($conn) {
        parent::__construct($conn);
        $this->ticketModel = new Ticket($this->conn);
    }

    public function handleRequest() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        
        $this->validateCsrfOrDie();
        
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['ticket_id'] ?? 0);

        switch ($action) {
            case 'create':
                $this->createTicket();
                break;
            case 'update_status':
                if (!$this->permissions['can_tickets']) {
                    $this->flash('error', 'Unauthorized access.');
                    break;
                }
                $this->updateStatus($id);
                break;
            case 'assign':
                if (!$this->permissions['can_tickets']) {
                    $this->flash('error', 'Unauthorized access.');
                    break;
                }
                $this->assignTicket($id);
                break;
            case 'add_comment':
                if (!$this->permissions['can_tickets']) {
                    $this->flash('error', 'Unauthorized access.');
                    break;
                }
                $this->addComment($id);
                break;
            case 'get_details':
                if (!$this->permissions['can_tickets']) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Unauthorized']);
                    exit();
                }
                $this->ajaxGetDetails($id);
                break;
            case 'send_report':
                if (!$this->permissions['can_tickets']) {
                    $this->flash('error', 'Unauthorized access.');
                    break;
                }
                $this->handleSendReport($id);
                break;
        }
        
        $this->redirect('dashboard.php?section=tickets');
    }

    private function createTicket() {
        if (!$this->permissions['can_tickets']) {
            $this->flash('error', 'Unauthorized access.');
            $this->redirect('dashboard.php');
        }
        $subject = trim($_POST['subject'] ?? '');
        if (mb_strlen($subject) < 3) {
            $this->flash('error', 'Subject must be at least 3 characters.');
            $this->redirect('dashboard.php?section=tickets');
        }
        $data = [
            'subject' => $subject,
            'description' => $_POST['description'] ?? '',
            'category' => $_POST['category'] ?? 'General',
            'priority' => $_POST['priority'] ?? 'Medium',
            'requester_id' => $_SESSION['user_id'] ?? null,
            'requester_name' => $_POST['name'] ?? null,
            'requester_email' => $_POST['email'] ?? null,
            'requester_phone' => $_POST['phone'] ?? null,
            'chapel_id' => $this->getAppliedChapelId($_POST['chapel_id'] ?? 0)
        ];

        $ticketNo = $this->ticketModel->create($data);
        if ($ticketNo) {
            // Capture the newly created ticket record for audit
            $newTicket = $this->ticketModel->getById($ticketNo);
            AuditLogger::logFromSession(
                $this->conn,
                'created',
                'Tickets',
                "Logged new ticket: $ticketNo",
                null,
                $newTicket
            );
            $this->flash('success', "Ticket $ticketNo created successfully.");
        } else {
            $this->flash('error', "Failed to create ticket.");
        }
    }

    private function updateStatus($id) {
        $status = $_POST['status'] ?? 'Open';
        $allowedStatuses = ['Open', 'In Progress', 'Pending Information', 'Resolved', 'Closed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->flash('error', 'Invalid status value.');
            return;
        }
        $before = $this->ticketModel->getById($id);

        if (!$before) {
            $this->flash('error', 'Ticket not found.');
            return;
        }

        if (!$this->checkChapelAccess($before['chapel_id'])) {
            $this->flash('error', 'Unauthorized access to this ticket.');
            return;
        }

        if ($this->ticketModel->update($id, ['status' => $status])) {
            // Capture old and new ticket states for audit
            $newTicket = $this->ticketModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Tickets',
                "Set ticket #$id to $status",
                $before,
                $newTicket
            );
            $terminalStates = ['Resolved', 'Closed'];
            $shouldNotify = in_array($status, $terminalStates, true)
                && !in_array((string)($before['status'] ?? ''), $terminalStates, true);

            $smsSent = $shouldNotify ? SmsService::sendTicketCompleted($this->conn, $id, $status) : false;
            $message = "Ticket status updated to $status.";
            if ($shouldNotify) {
                $message .= " SMS: " . ($smsSent ? 'sent ✅' : 'not sent ❌');
            }
            $this->flash('success', $message);
        } else {
            $this->flash('error', 'Failed to update ticket status.');
        }
    }

    private function assignTicket($id) {
        $userId = (int)($_POST['assigned_to'] ?? 0);
        $before = $this->ticketModel->getById($id);

        if (!$before) {
            $this->flash('error', 'Ticket not found.');
            return;
        }

        if (!$this->checkChapelAccess($before['chapel_id'])) {
            $this->flash('error', 'Unauthorized access to this ticket.');
            return;
        }

        if ($this->ticketModel->update($id, ['assigned_to' => $userId])) {
            $after = $this->ticketModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'updated',
                'Tickets',
                "Assigned ticket #$id to User $userId",
                $before,
                $after
            );
            $this->flash('success', "Ticket assigned.");
        } else {
            $this->flash('error', 'Failed to assign ticket.');
        }
    }

    private function addComment($id) {
        // Chapel access check — any user can comment but only on their own chapel's tickets
        $ticket = $this->ticketModel->getById($id);
        if (!$ticket) {
            $this->flash('error', 'Ticket not found.');
            return;
        }
        if (!$this->checkChapelAccess($ticket['chapel_id'])) {
            $this->flash('error', 'Unauthorized: you cannot comment on another chapel\'s ticket.');
            return;
        }

        $text = $_POST['comment'] ?? '';
        $isInternal = isset($_POST['is_internal']) ? 1 : 0;
        $userId = $_SESSION['user_id'] ?? null;

        if ($this->ticketModel->addComment($id, $userId, $text, $isInternal)) {
            $after = $this->ticketModel->getById($id);
            AuditLogger::logFromSession(
                $this->conn,
                'commented',
                'Tickets',
                "Added comment to ticket #{$id}",
                $ticket,
                $after
            );
            $this->flash('success', "Comment added.");
        } else {
            $this->flash('error', 'Failed to add comment.');
        }
    }

    /**
     * Sends a formatted ticket report email to a recipient (e.g. vendor, admin).
     * Uses the same mailer.php transport as other mail services.
     */
    private function sendTicketReportToEmail(int $id, string $recipientEmail, string $customNote = ''): bool {
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $ticket = $this->ticketModel->getById($id);
        if (!$ticket) return false;

        $mailerPath = __DIR__ . '/../mail/mailer.php';
        if (!function_exists('sendChurchEmail')) {
            if (!is_file($mailerPath)) return false;
            require_once $mailerPath;
        }

        $no      = htmlspecialchars($ticket['ticket_no'] ?? "#$id");
        $subject = htmlspecialchars($ticket['subject'] ?? 'N/A');
        $status  = htmlspecialchars($ticket['status'] ?? 'Unknown');
        $created = htmlspecialchars($ticket['created_at'] ?? '');
        $desc    = nl2br(htmlspecialchars($ticket['description'] ?? ''));

        $emailSubject = "📋 Ticket Report — {$no}";
        
        $noteHtml = "";
        if (!empty($customNote)) {
            $nc = nl2br(htmlspecialchars($customNote));
            $noteHtml = "<p style=\"font-weight:600;color:#c9a84c;margin:6px 0 6px;\">Note from Staff:</p>
                         <div style=\"background:#fff7ed;border:1px solid #ffedd5;border-radius:8px;padding:14px;margin-bottom:16px;font-style:italic;\">{$nc}</div>";
        }

        $emailBody = <<<HTML
        <!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;font-size:14px;color:#1e293b;">
        <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
            <tr><td style="background:#1e293b;padding:24px 32px;text-align:center;">
                <p style="margin:0;font-family:Georgia,serif;font-size:20px;font-weight:700;color:#c9a84c;">Cathedral Management System</p>
                <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.5);">Support Ticket Report</p>
            </td></tr>
            <tr><td style="padding:28px 32px;line-height:1.7;">
                {$noteHtml}
                <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                    <tr style="background:#f8fafc;"><td style="padding:10px 14px;font-weight:600;color:#64748b;width:35%;">Ticket No</td><td style="padding:10px 14px;">{$no}</td></tr>
                    <tr><td style="padding:10px 14px;font-weight:600;color:#64748b;">Subject</td><td style="padding:10px 14px;">{$subject}</td></tr>
                    <tr style="background:#f8fafc;"><td style="padding:10px 14px;font-weight:600;color:#64748b;">Status</td><td style="padding:10px 14px;">{$status}</td></tr>
                    <tr><td style="padding:10px 14px;font-weight:600;color:#64748b;">Created</td><td style="padding:10px 14px;">{$created}</td></tr>
                </table>
                <p style="font-weight:600;color:#64748b;margin:16px 0 6px;">Description:</p>
                <div style="background:#f8fafc;border-radius:8px;padding:14px;">{$desc}</div>
            </td></tr>
            <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;color:#94a3b8;">Automated report from Cathedral Management System.<br>Do not reply directly to this email.</p>
            </td></tr>
        </table>
        </td></tr></table>
        </body></html>
        HTML;

        $sent = sendChurchEmail($recipientEmail, $recipientEmail, $emailSubject, $emailBody);
        AuditLogger::logFromSession(
            $this->conn, 'shared', 'Tickets',
            "Sent ticket report #{$no} to {$recipientEmail} — " . ($sent ? 'success' : 'failed')
        );
        return $sent;
    }

    /**
     * AJAX context: Returns JSON representation of a ticket including its comments.
     */
    private function ajaxGetDetails(int $id) {
        $ticket = $this->ticketModel->getById($id);
        if (!$ticket) {
            echo json_encode(['error' => 'Ticket not found']);
            exit();
        }

        // Chapel isolation
        if (!$this->checkChapelAccess($ticket['chapel_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }

        $isInternal = in_array($this->role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff']);
        $comments = $this->ticketModel->getComments($id, $isInternal); // Internal staff see all notes
        
        header('Content-Type: application/json');
        echo json_encode([
            'ticket' => $ticket,
            'comments' => $comments,
            'current_user_id' => $_SESSION['user_id']
        ]);
        exit();
    }

    private function handleSendReport(int $id) {
        $ticket = $this->ticketModel->getById($id);
        if (!$ticket) {
            $this->flash('error', 'Ticket not found.');
            return;
        }

        // SECURITY GUARD: Ensure staff has access to this ticket's chapel
        if (!$this->checkChapelAccess($ticket['chapel_id'])) {
            $this->flash('error', 'Unauthorized access to this ticket.');
            return;
        }

        // HUMAN ERROR GUARD: Prevent spamming report emails
        $lastSentKey = "ticket_report_sent_" . $id;
        if (isset($_SESSION[$lastSentKey]) && (time() - $_SESSION[$lastSentKey] < 30)) {
            $this->flash('error', 'Please wait 30 seconds before resending this report.');
            return;
        }

        $email = $_POST['recipient_email'] ?? '';
        $note = $_POST['message'] ?? '';
        if (empty($email)) {
            $this->flash('error', 'Recipient email is required.');
            return;
        }

        if ($this->sendTicketReportToEmail($id, $email, $note)) {
            $_SESSION[$lastSentKey] = time();
            $this->flash('success', "Report for ticket #$id sent to $email successfully.");
        } else {
            $this->flash('error', "Failed to send report to $email. Please check system mailer configuration.");
        }
    }
}
?>
