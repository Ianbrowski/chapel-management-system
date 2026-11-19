<?php
/**
 * InquiryMailer
 * Handles automated email notifications for public service inquiries.
 */
class InquiryMailer {
    public static function sendStatusUpdate($email, $name, $category, $status, $adminNote = '') {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        if (!function_exists('sendChurchEmail')) {
            $path = __DIR__ . '/mailer.php';
            if (file_exists($path)) require_once $path;
            else return false;
        }

        $safeName = htmlspecialchars($name);
        $safeCat  = htmlspecialchars($category ?: 'Request');
        $safeStatus = htmlspecialchars($status);
        $safeNote = $adminNote ? ('<p style="background:#f8fafc; padding:15px; border-left:4px solid #c9a84c; font-style:italic;">' . nl2br(htmlspecialchars($adminNote)) . '</p>') : '';

        $subject = "Update on your {$safeCat} — {$safeStatus}";
        
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
                <p style="margin:0;font-size:15px;font-weight:700;color:#1a1200;">Inquiry Status Update</p>
            </td></tr>
            <tr><td style="padding:28px 32px;line-height:1.7;">
                <p>Hello <strong>{$safeName}</strong>,</p>
                <p>We are writing to inform you that your <strong>{$safeCat}</strong> has been updated to:</p>
                
                <div style="text-align:center; margin:25px 0;">
                    <span style="background:#1e293b; color:#c9a84c; padding:10px 25px; border-radius:30px; font-weight:800; font-size:16px; text-transform:uppercase; letter-spacing:1px;">{$safeStatus}</span>
                </div>

                {$safeNote}

                <p>If you have any further questions or need additional assistance, please feel free to visit the Parish Office.</p>
                <p style="margin-top:25px; border-top:1px solid #f1f5f9; padding-top:20px; font-size:12px; color:#64748b;">
                    Thank you and God bless!<br>
                    <strong>Parish Office Administration</strong>
                </p>
            </td></tr>
            <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;color:#94a3b8;">This is an automated notification. Please do not reply directly to this email.</p>
            </td></tr>
        </table>
        </td></tr></table>
        </body></html>
        HTML;

        return sendChurchEmail($email, $name, $subject, $body);
    }

    public static function sendAcknowledgment($email, $name, $category) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        if (!function_exists('sendChurchEmail')) {
            $path = __DIR__ . '/mailer.php';
            if (file_exists($path)) require_once $path;
            else return false;
        }

        $safeName = htmlspecialchars($name);
        $safeCat  = htmlspecialchars($category ?: 'Request');
        $subject  = "Request Received — Cathedral Management System";

        $body = <<<HTML
        <!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f1f5f9;font-family:'Helvetica Neue',Arial,sans-serif;font-size:14px;color:#1e293b;">
        <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
        <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
            <tr><td style="background:#1e293b;padding:24px 32px;text-align:center;">
                <p style="margin:0;font-family:Georgia,serif;font-size:20px;font-weight:700;color:#c9a84c;letter-spacing:0.04em;">Cathedral Management System</p>
            </td></tr>
            <tr><td style="background:#c9a84c;padding:14px 32px;">
                <p style="margin:0;font-size:15px;font-weight:700;color:#1a1200;">Request Acknowledgment</p>
            </td></tr>
            <tr><td style="padding:28px 32px;line-height:1.7;">
                <p>Hello <strong>{$safeName}</strong>,</p>
                <p>Thank you for reaching out to us. We have received your <strong>{$safeCat}</strong> and it is now in our queue for review.</p>
                <p>Our office staff will check the details and get back to you shortly if any further information is needed. You will receive another notification once your request has been processed.</p>
                <p style="margin-top:25px; border-top:1px solid #f1f5f9; padding-top:20px; font-size:12px; color:#64748b;">
                    Thank you and God bless!<br>
                    <strong>Parish Office Administration</strong>
                </p>
            </td></tr>
        </table>
        </td></tr></table>
        </body></html>
        HTML;

        return sendChurchEmail($email, $name, $subject, $body);
    }

}
