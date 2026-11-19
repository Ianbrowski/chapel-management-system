<?php
/**
 * EventRequestMailer
 *
 * Sends email notifications when an event request is approved or rejected.
 * Reads the requester_email column on the events table — set when the
 * requester fills in their email on the Request Event form.
 *
 * Usage (called automatically by DashboardController):
 *   EventRequestMailer::sendApproved($conn, $eventId, $pastorAssigned);
 *   EventRequestMailer::sendRejected($conn, $eventId, $rejectionNote);
 *
 * Silently skips if no requester_email is set on the event.
 */
class EventRequestMailer
{
    public static function sendApproved(mysqli $conn, int $eventId, string $pastorAssigned = ''): bool
    {
        $event = self::fetchEvent($conn, $eventId);
        if (!$event) return false;
        
        // Priority: 1. Guest Email (requester_email) 2. Registered User Email
        $to = null;
        if (!empty($event['requester_email'])) {
            $to = trim($event['requester_email']);
        } elseif (!empty($event['user_email'])) {
            // Send to any registered user who requested it, regardless of role
            // unless they are Admin/Cathedral Admin (optional exclusion)
            if (!in_array($event['role'], ['Admin', 'Cathedral Admin'])) {
                $to = trim($event['user_email']);
            }
        }

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Priority for Name: 1. Event Column (not existing yet) 2. Registered User Name 3. Fallback
        $rawName = $event['requester_name'] ?? 'Requester';
        // HTML-safe versions for the email body only
        $name     = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $title    = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
        $type     = htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8');
        $date     = date('F d, Y \a\t h:i A', strtotime($event['start_time']));
        $location = htmlspecialchars($event['chapel_location'] ?: 'To be confirmed', ENT_QUOTES, 'UTF-8');
        $pastor   = $pastorAssigned ? htmlspecialchars($pastorAssigned, ENT_QUOTES, 'UTF-8') : 'To be assigned';

        $subject = "✅ Event Request Approved — {$title}";
        $body    = self::wrap("Event Request Approved", <<<HTML
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Great news! Your event request has been <strong style="color:#15803d;">approved</strong> by the Cathedral Admin.</p>

            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;width:35%;">Event</td>
                    <td style="padding:10px 14px;">{$title}</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Type</td>
                    <td style="padding:10px 14px;">{$type}</td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Date &amp; Time</td>
                    <td style="padding:10px 14px;">{$date}</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Location</td>
                    <td style="padding:10px 14px;">{$location}</td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Assigned Priest</td>
                    <td style="padding:10px 14px;">{$pastor}</td>
                </tr>
            </table>

            <p>Please coordinate with the parish office for any further arrangements.</p>
        HTML);

        return self::send($to, $rawName, $subject, $body, $event['chapel_id'] ?? null);
    }

    public static function sendRejected(mysqli $conn, int $eventId, string $rejectionNote = ''): bool
    {
        $event = self::fetchEvent($conn, $eventId);
        if (!$event) return false;

        // Priority: 1. Guest Email (requester_email) 2. Registered User Email
        $to = null;
        if (!empty($event['requester_email'])) {
            $to = trim($event['requester_email']);
        } elseif (!empty($event['user_email'])) {
            if (!in_array($event['role'], ['Admin', 'Cathedral Admin'])) {
                $to = trim($event['user_email']);
            }
        }

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Raw name for PHPMailer SMTP header
        $rawName = $event['requester_name'] ?? 'Requester';
        // HTML-safe versions for body
        $name   = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $title  = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
        $type   = htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8');
        $date   = date('F d, Y \a\t h:i A', strtotime($event['start_time']));
        $reason = $rejectionNote ? htmlspecialchars($rejectionNote, ENT_QUOTES, 'UTF-8') : 'No reason provided.';

        $subject = "❌ Event Request Not Approved — {$title}";
        $body    = self::wrap("Event Request Not Approved", <<<HTML
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We regret to inform you that your event request has been <strong style="color:#b91c1c;">declined</strong>.</p>

            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;width:35%;">Event</td>
                    <td style="padding:10px 14px;">{$title}</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Type</td>
                    <td style="padding:10px 14px;">{$type}</td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Requested Date</td>
                    <td style="padding:10px 14px;">{$date}</td>
                </tr>
            </table>

            <div style="background:#fef2f2;border-left:4px solid #ef4444;padding:14px 18px;border-radius:0 8px 8px 0;margin:20px 0;">
                <strong style="color:#b91c1c;">Reason:</strong>
                <p style="margin:6px 0 0;color:#7f1d1d;">{$reason}</p>
            </div>

            <p>You are welcome to submit a new request with an adjusted date or details.
               Contact the parish office if you need further assistance.</p>
        HTML);

        return self::send($to, $rawName, $subject, $body, $event['chapel_id'] ?? null);
    }

    public static function sendAcknowledgment(mysqli $conn, int $eventId): bool
    {
        $event = self::fetchEvent($conn, $eventId);
        if (!$event) return false;

        $to = null;
        if (!empty($event['requester_email'])) {
            $to = trim($event['requester_email']);
        } elseif (!empty($event['user_email'])) {
            $to = trim($event['user_email']);
        }

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $rawName = $event['requester_name'] ?? 'Requester';
        $name    = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $title   = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
        $type    = htmlspecialchars($event['event_type'], ENT_QUOTES, 'UTF-8');
        $date    = date('F d, Y \a\t h:i A', strtotime($event['start_time']));

        $subject = "📩 Event Request Received — {$title}";
        $body    = self::wrap("Event Request Received", <<<HTML
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We have received your event request and it has been added to our review queue.</p>

            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;width:35%;">Event</td>
                    <td style="padding:10px 14px;">{$title}</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Type</td>
                    <td style="padding:10px 14px;">{$type}</td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Date &amp; Time</td>
                    <td style="padding:10px 14px;">{$date}</td>
                </tr>
            </table>

            <p>A Cathedral Admin will review your request shortly. You will receive an email notification once a decision has been made.</p>
            <p>Thank you and God bless!</p>
        HTML);

        return self::send($to, $rawName, $subject, $body, $event['chapel_id'] ?? null);
    }


    // ── Private ──────────────────────────────────────────────────────────────

    private static function fetchEvent(mysqli $conn, int $eventId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT e.event_id, e.title, e.event_type, e.start_time,
                    e.chapel_location, e.requester_email, e.chapel_id,
                    u.email AS user_email, u.role,
                    COALESCE(u.full_name, u.user) AS requester_name
             FROM events e
             LEFT JOIN users u ON u.id = e.requested_by
             WHERE e.event_id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private static function send(string $email, string $name, string $subject, string $body, $chapelId = null): bool
    {
        if (!function_exists('sendChurchEmail')) {
            $path = __DIR__ . '/mailer.php';
            if (!file_exists($path)) {
                return false;
            }
            require_once $path;
        }

        $sent = sendChurchEmail($email, $name, $subject, $body, $chapelId);
        if (!$sent) {
            error_log('Event request mail failed for recipient: ' . $email);
        }

        return $sent;
    }

    private static function wrap(string $heading, string $content): string
    {
        return <<<HTML
        <!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;font-size:14px;color:#1e293b;">
        <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
            <tr><td style="background:#1e293b;padding:24px 32px;text-align:center;">
                <p style="margin:0;font-family:Georgia,serif;font-size:20px;font-weight:700;color:#c9a84c;letter-spacing:0.04em;">Cathedral Management System</p>
                <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,0.5);">Iglesia Filipina Independiente</p>
            </td></tr>
            <tr><td style="background:#c9a84c;padding:14px 32px;">
                <p style="margin:0;font-size:15px;font-weight:700;color:#1a1200;">{$heading}</p>
            </td></tr>
            <tr><td style="padding:28px 32px;line-height:1.7;">{$content}</td></tr>
            <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;color:#94a3b8;">This is an automated message from the Cathedral Management System.<br>Please do not reply directly to this email.</p>
            </td></tr>
        </table>
        </td></tr></table>
        </body></html>
        HTML;
    }
}
