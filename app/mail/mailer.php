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

function detectEmailApiProvider(string $explicitProvider, string $apiKey): string {
    $p = strtolower(trim($explicitProvider));
    if (in_array($p, ['brevo', 'resend', 'sendgrid'], true)) {
        return $p;
    }
    if ($p === '' && $apiKey !== '') {
        if (strpos($apiKey, 'xkeysib-') === 0) return 'brevo';
        if (strpos($apiKey, 're_') === 0) return 'resend';
        if (strpos($apiKey, 'SG.') === 0) return 'sendgrid';
    }
    return '';
}

function sendViaEmailApi(string $provider, string $apiKey, array $params, string $baseUrlOverride = ''): bool {
    $provider = strtolower(trim($provider));
    $apiKey   = trim($apiKey);
    if ($apiKey === '') {
        $GLOBALS['lastMailError'] = 'Email API key is empty.';
        return false;
    }
    if (!function_exists('curl_init')) {
        $GLOBALS['lastMailError'] = 'cURL extension is not available.';
        return false;
    }

    $toEmail   = trim((string)($params['to'] ?? ''));
    $toName    = trim((string)($params['to_name'] ?? ''));
    $subject   = (string)($params['subject'] ?? '');
    $html      = (string)($params['html'] ?? '');
    $text      = (string)($params['text'] ?? '');
    $fromName  = trim((string)($params['from_name'] ?? 'IFI Cathedral System')) ?: 'IFI Cathedral System';
    $fromEmail = trim((string)($params['from_email'] ?? ''));
    $replyTo   = trim((string)($params['reply_to'] ?? ''));

    // Resend supports sandbox sender onboarding@resend.dev if no verified domain sender is set
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        if ($provider === 'resend') {
            $fromEmail = 'onboarding@resend.dev';
        } else {
            $GLOBALS['lastMailError'] = ucfirst($provider) . ' requires a verified Sender "From" Email (set EMAIL_FROM_EMAIL or Sender Email in Parish Settings).';
            error_log('Church API mailer skipped: ' . $GLOBALS['lastMailError']);
            return false;
        }
    }

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $endpoint = '';
    $payload = [];

    switch ($provider) {
        case 'brevo':
            $endpoint = $baseUrlOverride !== '' ? $baseUrlOverride : 'https://api.brevo.com/v3/smtp/email';
            $headers[] = 'api-key: ' . $apiKey;
            $toObj = ['email' => $toEmail];
            if ($toName !== '') $toObj['name'] = $toName;
            $payload = [
                'sender'      => ['name' => $fromName, 'email' => $fromEmail],
                'to'          => [$toObj],
                'subject'     => $subject,
                'htmlContent' => $html,
                'textContent' => $text !== '' ? $text : $subject,
            ];
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $payload['replyTo'] = ['email' => $replyTo, 'name' => $fromName];
            }
            break;

        case 'resend':
            $endpoint = $baseUrlOverride !== '' ? $baseUrlOverride : 'https://api.resend.com/emails';
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $safeName = str_replace(['"', '<', '>'], '', $fromName);
            $fromHeader = $safeName !== '' ? "{$safeName} <{$fromEmail}>" : $fromEmail;
            $payload = [
                'from'    => $fromHeader,
                'to'      => [$toEmail],
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text,
            ];
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $payload['reply_to'] = $replyTo;
            }
            break;

        case 'sendgrid':
            $endpoint = $baseUrlOverride !== '' ? $baseUrlOverride : 'https://api.sendgrid.com/v3/mail/send';
            $headers[] = 'Authorization: Bearer ' . $apiKey;
            $toObj = ['email' => $toEmail];
            if ($toName !== '') $toObj['name'] = $toName;
            $content = [];
            if ($text !== '') $content[] = ['type' => 'text/plain', 'value' => $text];
            $content[] = ['type' => 'text/html', 'value' => $html];
            $payload = [
                'personalizations' => [['to' => [$toObj]]],
                'from'             => ['email' => $fromEmail, 'name' => $fromName],
                'subject'          => $subject,
                'content'          => $content,
            ];
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $payload['reply_to'] = ['email' => $replyTo, 'name' => $fromName];
            }
            break;

        default:
            $GLOBALS['lastMailError'] = 'Unsupported email API provider: ' . $provider;
            return false;
    }

    $ch = curl_init();
    if ($ch === false) {
        $GLOBALS['lastMailError'] = 'Failed to initialize cURL session.';
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $GLOBALS['lastMailError'] = ucfirst($provider) . ' cURL error: ' . ($curlErr ?: 'Request failed');
        error_log('Church API mail failed: ' . $GLOBALS['lastMailError']);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded = json_decode((string)$response, true);
        $apiMsg = '';
        if (is_array($decoded)) {
            if (!empty($decoded['message'])) {
                $apiMsg = (string)$decoded['message'];
            } elseif (!empty($decoded['error']['message'])) {
                $apiMsg = (string)$decoded['error']['message'];
            } elseif (!empty($decoded['errors'][0]['message'])) {
                $apiMsg = (string)$decoded['errors'][0]['message'];
            }
        }
        $GLOBALS['lastMailError'] = ucfirst($provider) . " API HTTP {$httpCode}" . ($apiMsg !== '' ? ": {$apiMsg}" : '');
        error_log('Church API mail failed: ' . $GLOBALS['lastMailError']);
        return false;
    }

    $GLOBALS['lastMailTransport'] = strtoupper($provider) . ' API';
    return true;
}

function sendViaEmailWebhook($webhookUrl, $secret, array $payload) {
    $webhookUrl = trim((string)$webhookUrl);
    if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        $GLOBALS['lastMailError'] = 'Invalid Email Webhook URL.';
        error_log('Church webhook mailer skipped: invalid URL.');
        return false;
    }

    $scheme = strtolower((string)parse_url($webhookUrl, PHP_URL_SCHEME));
    $host = strtolower((string)parse_url($webhookUrl, PHP_URL_HOST));
    if ($scheme !== 'https' || $host === '') {
        $GLOBALS['lastMailError'] = 'Email Webhook URL must use https://.';
        error_log('Church webhook mailer skipped: HTTPS required.');
        return false;
    }

    // Block loopback, private, and link-local destinations to prevent SSRF
    $resolvedIps = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $resolvedIps[] = $host;
    } else {
        $aRecords = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($aRecords)) {
            foreach ($aRecords as $rec) {
                if (!empty($rec['ip'])) $resolvedIps[] = $rec['ip'];
                if (!empty($rec['ipv6'])) $resolvedIps[] = $rec['ipv6'];
            }
        }
        $fallbackIp = @gethostbyname($host);
        if ($fallbackIp && $fallbackIp !== $host) {
            $resolvedIps[] = $fallbackIp;
        }
    }
    foreach (array_unique($resolvedIps) as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $GLOBALS['lastMailError'] = 'Email Webhook URL cannot target private or internal network addresses.';
            error_log('Church webhook mailer blocked SSRF target IP: ' . $ip);
            return false;
        }
    }

    if (!function_exists('curl_init')) {
        $GLOBALS['lastMailError'] = 'cURL extension is not available.';
        error_log('Church webhook mailer failed: curl_init unavailable.');
        return false;
    }

    if ($secret !== '') {
        $payload['secret'] = $secret;
    }

    $ch = curl_init();
    if ($ch === false) {
        $GLOBALS['lastMailError'] = 'Failed to initialize cURL session.';
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, [
        CURLOPT_URL            => $webhookUrl,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $GLOBALS['lastMailError'] = 'Webhook cURL error: ' . ($curlErr ?: 'Request failed');
        error_log('Church webhook mail failed: ' . $GLOBALS['lastMailError']);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 400) {
        $GLOBALS['lastMailError'] = "Webhook returned HTTP {$httpCode}.";
        error_log("Church webhook mail failed: HTTP {$httpCode}");
        return false;
    }

    $trimmed = ltrim((string)$response);
    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        if ((isset($decoded['ok']) && !$decoded['ok']) || (isset($decoded['success']) && !$decoded['success'])) {
            $errMsg = (string)($decoded['error'] ?? $decoded['message'] ?? 'Webhook rejected request');
            $GLOBALS['lastMailError'] = 'Webhook error: ' . $errMsg;
            error_log('Church webhook mail rejected: ' . $errMsg);
            return false;
        }
        $GLOBALS['lastMailTransport'] = 'HTTPS Webhook';
        return true;
    }

    // Detect Google Sign-In / HTML error page when Apps Script deployment isn't set to "Anyone"
    if (stripos($trimmed, '<html') !== false || stripos($trimmed, '<!doctype') !== false) {
        if (stripos($trimmed, 'ServiceLogin') !== false || stripos($trimmed, 'accounts.google.com') !== false) {
            $GLOBALS['lastMailError'] = 'Google Apps Script access must be set to "Anyone" (received sign-in page).';
        } else {
            $GLOBALS['lastMailError'] = 'Webhook returned HTML instead of JSON response. Ensure Apps Script returns ContentService JSON.';
        }
        error_log('Church webhook mail failed: ' . $GLOBALS['lastMailError']);
        return false;
    }

    $GLOBALS['lastMailTransport'] = 'HTTPS Webhook';
    return true;
}

function sendChurchEmail($recipientEmail, $recipientName, $subject, $body, $chapelId = null) {
    global $smtpEnabled, $smtpHost, $smtpUser, $smtpPass, $smtpSecure, $smtpPort, $smtpFromName;
    global $emailProvider, $emailApiKey, $emailFromEmail, $emailFromName, $emailApiBaseUrl;
    global $emailWebhookUrl, $emailWebhookSecret, $conn;

    $GLOBALS['lastMailError'] = '';
    $GLOBALS['lastMailTransport'] = '';

    // Default configuration (Global Fallback from config.php / Render env vars)
    $config = [
        'enabled'        => $smtpEnabled,
        'host'           => $smtpHost,
        'user'           => $smtpUser,
        'pass'           => $smtpPass,
        'secure'         => $smtpSecure,
        'port'           => $smtpPort,
        'from_name'      => !empty($emailFromName) ? $emailFromName : $smtpFromName,
        'from_email'     => (string)($emailFromEmail ?? ''),
        'email_provider' => (string)($emailProvider ?? ''),
        'email_api_key'  => (string)($emailApiKey ?? ''),
        'api_base_url'   => (string)($emailApiBaseUrl ?? ''),
        'webhook_url'    => (string)($emailWebhookUrl ?? ''),
        'webhook_secret' => (string)($emailWebhookSecret ?? ''),
    ];

    // Check for Global Database Overrides (Main Cathedral Settings)
    if ($conn) {
        $parishRes = @$conn->query("SELECT * FROM parish_contact_info ORDER BY info_id DESC LIMIT 1");
        if ($parishRes && $row = $parishRes->fetch_assoc()) {
            if (!empty($row['email_provider'])) {
                $config['email_provider'] = strtolower(trim((string)$row['email_provider']));
            }
            if (!empty($row['email_api_key'])) {
                $config['email_api_key'] = trim((string)$row['email_api_key']);
            }
            if (!empty($row['email_webhook_url'])) {
                $config['webhook_url'] = trim((string)$row['email_webhook_url']);
            }
            if (!empty($row['email_webhook_secret'])) {
                $config['webhook_secret'] = (string)$row['email_webhook_secret'];
            }
            if (!empty($row['smtp_host']) && !empty($row['smtp_user'])) {
                $config['host'] = $row['smtp_host'];
                $config['port'] = (int)$row['smtp_port'];
                $config['user'] = $row['smtp_user'];
                if (!empty($row['smtp_pass'])) $config['pass'] = $row['smtp_pass'];
                $config['secure'] = $row['smtp_secure'] ?: 'tls';
                if (!empty($row['from_name'])) $config['from_name'] = $row['from_name'];
                if (!empty($row['from_email'])) $config['from_email'] = $row['from_email'];
                $config['enabled'] = !empty($config['host']) && !empty($config['user']) && !empty($config['pass']);
            } else {
                if (!empty($row['from_name'])) $config['from_name'] = $row['from_name'];
                if (!empty($row['from_email'])) $config['from_email'] = $row['from_email'];
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
                $config['enabled']    = true;
                $config['host']       = $row['smtp_host'];
                $config['port']       = (int)$row['smtp_port'];
                $config['user']       = $row['smtp_user'];
                $config['pass']       = $row['smtp_pass'];
                $config['secure']     = $row['smtp_secure'] ?: 'tls';
                $config['from_name']  = $row['from_name'] ?: $row['from_email'] ?: $config['from_name'];
                $config['from_email'] = $row['from_email'] ?: $row['smtp_user'];
            } elseif ($row && (!empty($row['from_name']) || !empty($row['from_email']))) {
                if (!empty($row['from_name'])) $config['from_name'] = $row['from_name'];
                if (!empty($row['from_email'])) $config['from_email'] = $row['from_email'];
            }
        }
    }

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['lastMailError'] = 'Invalid recipient email address.';
        error_log("Church mailer skipped: invalid recipient email ($recipientEmail)");
        return false;
    }

    $plainText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$body), ENT_QUOTES, 'UTF-8')));
    $fromEmail = trim((string)($config['from_email'] ?: $config['user'] ?: ''));
    $replyTo   = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : '';

    $selectedProvider = strtolower(trim((string)$config['email_provider']));
    $apiProvider = detectEmailApiProvider($selectedProvider, (string)$config['email_api_key']);

    // 1. HTTPS Email API (Brevo / Resend / SendGrid) — works on Render Free tier over port 443
    if ($apiProvider !== '' && $selectedProvider !== 'webhook' && $selectedProvider !== 'smtp') {
        $apiSent = sendViaEmailApi($apiProvider, (string)$config['email_api_key'], [
            'to'         => $recipientEmail,
            'to_name'    => (string)$recipientName,
            'subject'    => (string)$subject,
            'html'       => (string)$body,
            'text'       => $plainText,
            'from_name'  => (string)($config['from_name'] ?: 'IFI Cathedral System'),
            'from_email' => $fromEmail,
            'reply_to'   => $replyTo,
        ], (string)$config['api_base_url']);

        if ($apiSent) {
            return true;
        }
        // If user explicitly pinned an API provider, surface its error cleanly without hanging on blocked SMTP
        if (in_array($selectedProvider, ['brevo', 'resend', 'sendgrid'], true)) {
            return false;
        }
    }

    // 2. HTTPS Email Webhook (Google Apps Script / Relay) — also works over port 443 on Render
    if (!empty($config['webhook_url']) && $selectedProvider !== 'smtp') {
        $webhookSent = sendViaEmailWebhook($config['webhook_url'], $config['webhook_secret'], [
            'to'        => $recipientEmail,
            'to_name'   => (string)$recipientName,
            'subject'   => (string)$subject,
            'html'      => (string)$body,
            'text'      => $plainText,
            'from_name' => (string)($config['from_name'] ?: 'IFI Cathedral System'),
            'reply_to'  => $replyTo,
        ]);
        if ($webhookSent) {
            return true;
        }
        if ($selectedProvider === 'webhook' || empty($config['enabled'])) {
            return false;
        }
    }

    if ($selectedProvider === 'webhook' && empty($config['webhook_url'])) {
        $GLOBALS['lastMailError'] = 'Webhook transport is selected, but Email Webhook URL is empty.';
        return false;
    }

    if (empty($config['enabled'])) {
        if ($GLOBALS['lastMailError'] === '') {
            $GLOBALS['lastMailError'] = 'No outgoing mail transport is configured (set Email API, Webhook URL, or SMTP).';
        }
        error_log("Church mailer skipped: no mail transport enabled for target (Chapel ID: " . ($chapelId ?: 'Global') . ")");
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
        $mail->Timeout    = 10;

        // Recipients
        $mail->CharSet = 'UTF-8';
        $smtpFromEmail = !empty($config['from_email']) ? $config['from_email'] : $config['user'];
        $mail->setFrom($smtpFromEmail, $config['from_name']);
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        $GLOBALS['lastMailTransport'] = 'SMTP';
        return true;
    } catch (Exception $e) {
        $GLOBALS['lastMailError'] = 'SMTP error: ' . $e->getMessage();
        error_log('Church mail send failed: ' . $e->getMessage());
        return false;
    }
}
?>
