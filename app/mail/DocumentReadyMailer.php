<?php
class DocumentReadyMailer {
    public static function sendPickupNotification($email, $name, $documentType, $pickupDate) {
        // Validate email before attempting to send
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('DocumentReadyMailer: invalid recipient email: ' . $email);
            return false;
        }

        if (!function_exists('sendChurchEmail')) {
            $path = __DIR__ . '/mailer.php';
            if (!file_exists($path)) return false;
            require_once $path;
        }

        // Keep raw $name for the SMTP "To" header — PHPMailer handles encoding
        // Only apply htmlspecialchars for embedding in the HTML body
        $bodyName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $bodyDocType = htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8');
        $bodyPickupDate = htmlspecialchars($pickupDate, ENT_QUOTES, 'UTF-8');

        $subject = "✅ Your Document is Ready for Pickup — {$bodyDocType}";
        $body = <<<HTML
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
                <p style="margin:0;font-size:15px;font-weight:700;color:#1a1200;">Document Ready for Pickup</p>
            </td></tr>
            <tr><td style="padding:28px 32px;line-height:1.7;">
                <p>Dear <strong>{$bodyName}</strong>,</p>
                <p>Great news! The document you requested is now officially printed, signed, and ready to be picked up at the parish office.</p>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr style="background:#f8fafc;">
                        <td style="padding:10px 14px;font-weight:600;color:#64748b;width:40%;">Document Type</td>
                        <td style="padding:10px 14px;font-weight:600;color:#1e293b;">{$bodyDocType}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 14px;font-weight:600;color:#64748b;">Pickup Date/Time</td>
                        <td style="padding:10px 14px;font-weight:600;color:#16a34a;">{$bodyPickupDate}</td>
                    </tr>
                </table>
                <p>Please bring a valid ID when you come to claim your document.</p>
            </td></tr>
            <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;color:#94a3b8;">This is an automated message from the Cathedral Management System.<br>Please do not reply directly to this email.</p>
            </td></tr>
        </table>
        </td></tr></table>
        </body></html>
        HTML;

        // Pass raw $name to sendChurchEmail — PHPMailer encodes it correctly for the mail header
        return sendChurchEmail($email, $name, $subject, $body);
    }
}
?>
