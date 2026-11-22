<?php

/**
 * SmsService
 *
 * Handles automated SMS notifications.
 * Defaults to mock mode and can be switched to a PH provider such as Semaphore.
 */
class SmsService
{
    private static function getLogPath(): string
    {
        return (defined('BASE_PATH') ? BASE_PATH : (__DIR__ . '/../..')) . '/storage/logs/sms_preview.log';
    }

    /**
     * Generic send method
     */
    public static function send(string $to, string $message): bool
    {
        if (empty($to)) return false;

        $provider = self::getConfig('sms_provider', 'mock');
        $normalizedNumber = self::normalizePhoneNumber($to);

        if ($provider === 'semaphore') {
            return self::sendViaSemaphore($normalizedNumber, $message);
        }

        if ($provider === 'iprog_tech') {
            return self::sendViaIprogTech($normalizedNumber, $message);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] TO: {$normalizedNumber}\nMESSAGE: \"{$message}\"\nSTATUS: MOCK_SENT\nPROVIDER: {$provider}\n" . str_repeat('-', 40) . "\n";

        $dir = dirname(self::getLogPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents(self::getLogPath(), $logEntry, FILE_APPEND) !== false;
    }

    /**
     * Notify user of Event Approval
     */
    public static function sendEventApproved(mysqli $conn, int $eventId, string $pastorName = ''): bool
    {
        $event = self::fetchEvent($conn, $eventId);
        if (!$event || empty($event['requester_phone'])) return false;

        $name = self::resolveSmsRecipientName($event);
        $title = $event['title'];
        $date = date('M d, Y @ h:i A', strtotime($event['start_time']));
        
        $msg = "Hi {$name}! Great news, your request for '{$title}' on {$date} has been APPROVED by the Cathedral Admin. See you there!";
        
        return self::send($event['requester_phone'], $msg);
    }

    /**
     * Notify user of Event Rejection
     */
    public static function sendEventRejected(mysqli $conn, int $eventId, string $reason = ''): bool
    {
        $event = self::fetchEvent($conn, $eventId);
        if (!$event || empty($event['requester_phone'])) return false;

        $name = self::resolveSmsRecipientName($event);
        $title = $event['title'];
        $reasonText = $reason ? " Reason: {$reason}" : "";

        $msg = "Hello {$name}. Regretfully, your request for '{$title}' was NOT approved at this time.{$reasonText} Please contact the office for details.";

        return self::send($event['requester_phone'], $msg);
    }

    /**
     * Notify user of Document Pickup
     */
    public static function sendPickupNotice(string $phone, string $name, string $docType): bool
    {
        $msg = "Hi {$name}! Your requested {$docType} is now ready for pickup at the Parish Office. Please bring your valid ID.";
        return self::send($phone, $msg);
    }

    /**
     * Notify requester that a support ticket has been completed.
     */
    public static function sendTicketCompleted(mysqli $conn, int $ticketId, string $status = 'Resolved'): bool
    {
        $ticket = self::fetchTicket($conn, $ticketId);
        if (!$ticket || empty($ticket['requester_phone'])) return false;

        $name = trim((string)($ticket['requester_name'] ?? ''));
        if ($name === '') {
            $name = 'Parishioner';
        }

        $ticketNo = $ticket['ticket_no'] ?? ('#' . $ticketId);
        $subject = $ticket['subject'] ?? 'request';
        $statusLabel = strtoupper($status);
        $msg = "Hi {$name}! Your parish support ticket {$ticketNo} for '{$subject}' is now {$statusLabel}. If you still need help, please contact the parish office.";

        return self::send($ticket['requester_phone'], $msg);
    }

    /**
     * Notify requester that their public inquiry has been updated.
     */
    public static function sendInquiryUpdated(mysqli $conn, int $inquiryId, string $status): bool
    {
        $stmt = $conn->prepare("SELECT full_name, phone, service_category FROM public_inquiries WHERE inquiry_id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $inquiryId);
        $stmt->execute();
        $inq = $stmt->get_result()->fetch_assoc();
        
        if (!$inq || empty($inq['phone'])) return false;

        $name = trim($inq['full_name'] ?: 'Parishioner');
        $cat = $inq['service_category'] ?: 'request';
        $statusLabel = strtoupper($status);

        $msg = "Hi {$name}! Your {$cat} inquiry at our Parish Office is now {$statusLabel}. Thank you!";
        
        return self::send($inq['phone'], $msg);
    }

    private static function resolveSmsRecipientName(array $event): string
    {
        $staffRoles = ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff'];
        $isStaffEncoded = in_array((string)($event['role'] ?? ''), $staffRoles, true);

        if (!empty($event['requester_name'])) {
            return trim((string)$event['requester_name']);
        }
        if (!empty($event['cert_person_name'])) {
            return trim((string)$event['cert_person_name']);
        }
        if (!$isStaffEncoded && !empty($event['account_name'])) {
            return trim((string)$event['account_name']);
        }
        return 'Parishioner';
    }

    private static function fetchEvent(mysqli $conn, int $eventId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT e.*,
                    u.role,
                    COALESCE(NULLIF(u.full_name, ''), u.user) AS account_name
             FROM events e
             LEFT JOIN users u ON u.id = e.requested_by
             WHERE e.event_id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function fetchTicket(mysqli $conn, int $ticketId): ?array
    {
        $stmt = $conn->prepare(
            "SELECT ticket_no, subject, requester_phone,
                    COALESCE(requester_name, '') AS requester_name
             FROM tickets
             WHERE ticket_id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function sendViaSemaphore(string $to, string $message): bool
    {
        $apiKey = (string) self::getConfig('sms_api_key', '');
        if ($apiKey === '' || !function_exists('curl_init')) {
            return false;
        }

        $senderName = (string) self::getConfig('sms_sender_name', 'SEMAPHORE');
        $endpoint = (string) self::getConfig('sms_base_url', 'https://api.semaphore.co/api/v4/messages');

        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        $payload = http_build_query([
            'apikey' => $apiKey,
            'number' => $to,
            'message' => $message,
            'sendername' => $senderName,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            return false;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded[0]['status'])) {
            return false;
        }

        return in_array(strtolower((string) $decoded[0]['status']), ['queued', 'pending', 'sent'], true);
    }

    private static function sendViaIprogTech(string $to, string $message): bool
    {
        $apiKey = (string) self::getConfig('sms_api_key', '');
        if ($apiKey === '' || !function_exists('curl_init')) {
            return false;
        }

        $endpoint = (string) self::getConfig('sms_base_url', 'https://sms.iprogtech.com/api/v1/sms_messages');

        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        $payload = json_encode([
            'api_token' => $apiKey,
            'phone_number' => $to,
            'message' => $message,
            'sms_provider' => 0 // Explicitly set to default provider
        ]);

        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false, // Bypass SSL verification for local environments
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            error_log("IPROG SMS CURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log("IPROG SMS Error: Invalid JSON response: " . $response);
            return false;
        }

        // IPROG returns { "status": 200, "message": "...", "message_id": "..." }
        $status = (int)($decoded['status'] ?? 0);
        if ($status !== 200 && $status !== 201) {
            error_log("IPROG SMS Error: Status {$status} - " . ($decoded['message'] ?? 'Unknown Error'));
            return false;
        }
        return true;
    }

    private static function normalizePhoneNumber(string $number): string
    {
        $number = preg_replace('/[^\d+]/', '', trim($number)) ?? '';
        if ($number === '') {
            return '';
        }

        if (strpos($number, '+63') === 0) {
            return '63' . substr($number, 3);
        }

        if (strpos($number, '09') === 0 && strlen($number) === 11) {
            return '63' . substr($number, 1);
        }

        if (strpos($number, '9') === 0 && strlen($number) === 10) {
            return '63' . $number;
        }

        return ltrim($number, '+');
    }

    private static function getConfig(string $key, $default = null)
    {
        $envKey = strtoupper($key);
        $envValue = getenv($envKey);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        $globalMap = [
            'sms_provider' => 'smsProvider',
            'sms_api_key' => 'smsApiKey',
            'sms_sender_name' => 'smsSenderName',
            'sms_base_url' => 'smsBaseUrl',
            'sms_country' => 'smsCountry',
        ];

        $globalKey = $globalMap[$key] ?? $key;
        return $GLOBALS[$globalKey] ?? $GLOBALS[$key] ?? $default;
    }
}
