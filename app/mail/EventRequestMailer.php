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

        $recipient = self::resolveRecipient($event);
        $to = $recipient['email'];
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $rawName  = $recipient['name'];
        $name     = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $title    = htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type     = htmlspecialchars((string)($event['event_type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $date     = date('F d, Y \a\t h:i A', strtotime((string)$event['start_time']));
        $location = htmlspecialchars((string)($event['chapel_location'] ?: 'To be confirmed'), ENT_QUOTES, 'UTF-8');
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

        $recipient = self::resolveRecipient($event);
        $to = $recipient['email'];
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $isCert = self::isCertificateEvent($event);
        $rawName = $recipient['name'];
        $name    = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $title   = htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type    = htmlspecialchars($isCert ? 'Ecclesiastical Certificate' : (string)($event['event_type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $date    = $isCert
            ? date('F d, Y', strtotime((string)$event['start_time']))
            : date('F d, Y \a\t h:i A', strtotime((string)$event['start_time']));
        $reason  = $rejectionNote ? htmlspecialchars($rejectionNote, ENT_QUOTES, 'UTF-8') : 'No reason provided.';

        $heading   = $isCert ? 'Certificate Application Not Approved' : 'Event Request Not Approved';
        $subject   = "❌ {$heading} — {$title}";
        $itemLabel = $isCert ? 'Document' : 'Event';
        $dateLabel = $isCert ? 'Target Pickup Date' : 'Requested Date';
        $noun      = $isCert ? 'certificate application' : 'event request';

        $body = self::wrap($heading, <<<HTML
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We regret to inform you that your {$noun} has been <strong style="color:#b91c1c;">declined</strong>.</p>

            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;width:35%;">{$itemLabel}</td>
                    <td style="padding:10px 14px;">{$title}</td>
                </tr>
                <tr>
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">Type</td>
                    <td style="padding:10px 14px;">{$type}</td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:10px 14px;font-weight:600;color:#64748b;">{$dateLabel}</td>
                    <td style="padding:10px 14px;">{$date}</td>
                </tr>
            </table>

            <div style="background:#fef2f2;border-left:4px solid #ef4444;padding:14px 18px;border-radius:0 8px 8px 0;margin:20px 0;">
                <strong style="color:#b91c1c;">Reason:</strong>
                <p style="margin:6px 0 0;color:#7f1d1d;">{$reason}</p>
            </div>

            <p>You are welcome to submit a new request with updated details.
               Contact the parish office if you need further assistance.</p>
        HTML);

        return self::send($to, $rawName, $subject, $body, $event['chapel_id'] ?? null);
    }

    public static function sendAcknowledgment(mysqli $conn, int $eventId): bool
    {
        $event = self::fetchEvent($conn, $eventId);
        if (!$event) return false;

        $recipient = self::resolveRecipient($event);
        $to = $recipient['email'];
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $isCert = self::isCertificateEvent($event);
        $rawName = $recipient['name'];
        $name    = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        $title   = htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type    = htmlspecialchars($isCert ? 'Ecclesiastical Certificate' : (string)($event['event_type'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($isCert) {
            $pickupDate = date('F d, Y', strtotime((string)$event['start_time']));
            $purposeRow = '';
            if (!empty($event['cert_purpose'])) {
                $purposeSafe = htmlspecialchars((string)$event['cert_purpose'], ENT_QUOTES, 'UTF-8');
                $purposeRow = "<tr><td style=\"padding:10px 14px;font-weight:600;color:#64748b;\">Purpose</td><td style=\"padding:10px 14px;\">{$purposeSafe}</td></tr>";
            }
            $subject = "📄 Certificate Application Received — {$title}";
            $body    = self::wrap("Certificate Application Received", <<<HTML
                <p>Dear <strong>{$name}</strong>,</p>
                <p>We have received your certificate application and queued it for archival verification and preparation.</p>

                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr style="background:#f8fafc;">
                        <td style="padding:10px 14px;font-weight:600;color:#64748b;width:35%;">Document</td>
                        <td style="padding:10px 14px;">{$title}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;font-weight:600;color:#64748b;">Type</td>
                        <td style="padding:10px 14px;">{$type}</td>
                    </tr>
                    {$purposeRow}
                    <tr style="background:#f8fafc;">
                        <td style="padding:10px 14px;font-weight:600;color:#64748b;">Target Pickup Date</td>
                        <td style="padding:10px 14px;">{$pickupDate}</td>
                    </tr>
                </table>

                <p>The Parish Office will verify the record and send another email once your certificate is ready for pickup.</p>
                <p>Thank you and God bless!</p>
            HTML);
        } else {
            $date    = date('F d, Y \a\t h:i A', strtotime((string)$event['start_time']));
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
        }

        return self::send($to, $rawName, $subject, $body, $event['chapel_id'] ?? null);
    }


    // ── Private ──────────────────────────────────────────────────────────────

    private static function isCertificateEvent(array $event): bool
    {
        return ((int)($event['service_id'] ?? 0) === 8)
            || strcasecmp((string)($event['event_type'] ?? ''), 'Ecclesiastical Certificates') === 0
            || stripos((string)($event['title'] ?? ''), 'Certificate') !== false;
    }

    private static function resolveRecipient(array $event): array
    {
        $staffRoles = ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'];
        $isStaffEncoded = in_array((string)($event['role'] ?? ''), $staffRoles, true);

        $email = null;
        if (!empty($event['requester_email'])) {
            $email = trim((string)$event['requester_email']);
        } elseif (!$isStaffEncoded && !empty($event['user_email'])) {
            $email = trim((string)$event['user_email']);
        }

        $name = '';
        if (!empty($event['requester_name'])) {
            $name = trim((string)$event['requester_name']);
        } elseif (!empty($event['cert_person_name'])) {
            $name = trim((string)$event['cert_person_name']);
        } elseif (!$isStaffEncoded && !empty($event['account_name'])) {
            $name = trim((string)$event['account_name']);
        }
        if ($name === '') {
            $name = 'Parishioner';
        }

        return [
            'email'            => $email,
            'name'             => $name,
            'is_staff_encoded' => $isStaffEncoded,
        ];
    }

    private static function fetchEvent(mysqli $conn, int $eventId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT e.*,
                    u.email AS user_email, u.role,
                    COALESCE(NULLIF(u.full_name, ''), u.user) AS account_name
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
