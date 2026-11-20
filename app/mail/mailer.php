<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

foreach ([
    __DIR__ . '/../../src/Exception.php',
    __DIR__ . '/../../src/PHPMailer.php',
    __DIR__ . '/../../src/SMTP.php',
] as $dependency) {
    if (!is_file($dependency)) {
        throw new RuntimeException('Missing mail dependency: ' . $dependency);
    }
    require_once $dependency;
}

function sendChurchEmail($recipientEmail, $recipientName, $subject, $body, $chapelId = null) {
    global $smtpEnabled, $smtpHost, $smtpUser, $smtpPass, $smtpSecure, $smtpPort, $smtpFromName, $conn;

    // Default configuration (Global Fallback from config.php)
    $config = [
        'enabled'   => $smtpEnabled,
        'host'      => $smtpHost,
        'user'      => $smtpUser,
        'pass'      => $smtpPass,
        'secure'    => $smtpSecure,
        'port'      => $smtpPort,
        'from_name' => $smtpFromName
    ];

    // Check for Global Database Overrides (Main Cathedral Settings)
    if ($conn) {
        $parishRes = $conn->query("SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name FROM parish_contact_info ORDER BY info_id DESC LIMIT 1");
        if ($parishRes && $row = $parishRes->fetch_assoc()) {
            if (!empty($row['smtp_host']) && !empty($row['smtp_user'])) {
                $config['host'] = $row['smtp_host'];
                $config['port'] = (int)$row['smtp_port'];
                $config['user'] = $row['smtp_user'];
                if (!empty($row['smtp_pass'])) $config['pass'] = $row['smtp_pass'];
                $config['secure'] = $row['smtp_secure'] ?: 'tls';
                if (!empty($row['from_name'])) $config['from_name'] = $row['from_name'];
                if (!empty($row['from_email'])) $config['from_email'] = $row['from_email'];
                $config['enabled'] = !empty($config['host']) && !empty($config['user']) && !empty($config['pass']);
            }
        }
    }

    // Check for Chapel-Specific Overrides
    if ($chapelId !== null && $conn) {
        $stmt = $conn->prepare("SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name FROM chapels WHERE chapel_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $chapelId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && !empty($row['smtp_host']) && !empty($row['smtp_user']) && !empty($row['smtp_pass'])) {
                $config = [
                    'enabled'   => true,
                    'host'      => $row['smtp_host'],
                    'port'      => (int)$row['smtp_port'],
                    'user'      => $row['smtp_user'],
                    'pass'      => $row['smtp_pass'],
                    'secure'    => $row['smtp_secure'] ?: 'tls',
                    'from_name' => $row['from_name'] ?: $row['from_email'] ?: $smtpFromName,
                    'from_email' => $row['from_email'] ?: $row['smtp_user']
                ];
            }
        }
    }

    if (empty($config['enabled'])) {
        error_log("Church mailer skipped: SMTP not enabled for target (Chapel ID: " . ($chapelId ?: 'Global') . ")");
        return false;
    }

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("Church mailer skipped: invalid recipient email ($recipientEmail)");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = ($config['secure'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->Timeout    = 15;

        // Recipients
        $mail->CharSet = 'UTF-8';
        $fromEmail = $config['from_email'] ?? $config['user'];
        $mail->setFrom($fromEmail, $config['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Church mail send failed: ' . $e->getMessage());
        return false;
    }
}
?>
