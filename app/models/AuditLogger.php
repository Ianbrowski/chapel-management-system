<?php
/**
 * AuditLogger — Lightweight service for writing audit trail entries.
 */
class AuditLogger
{
    /**
     * Convenience wrapper — pulls session values automatically.
     * 
     * @param mysqli $conn
     * @param string $action    created | updated | deleted | login | logout | failed_login
     * @param string $module    Events | Records | Users | Chapels | Finance | Merchandise | Bulletin | Auth | Inquiries | Tickets | System
     * @param string $target    Human-readable description of the record (e.g. "User: admin")
     * @param array|null $oldValues Snapshot before change
     * @param array|null $newValues Snapshot after change
     * @param int|null $targetChapelId  Override chapel_id (use when the action targets a specific chapel regardless of session)
     */
    public static function logFromSession(
        mysqli $conn,
        string $action,
        string $module,
        string $target,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $targetChapelId = null
    ): bool {
        if (!isset($_SESSION)) {
            session_start();
        }

        $userId   = $_SESSION['user_id']  ?? 0;
        $username = $_SESSION['username'] ?? 'guest';
        $role     = $_SESSION['role']     ?? 'Visitor';
        $chapelId = $targetChapelId ?? ($_SESSION['chapel_id'] ?? null);

        return self::log($conn, $action, $module, $target, $oldValues, $newValues, (int)$userId, $username, $role, $chapelId);
    }

    /**
     * Log actions performed without an active session (e.g. self-registration, public inquiries).
     */
    public static function logAnonymous(
        mysqli $conn,
        string $action,
        string $module,
        string $target,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $chapelId = null
    ): bool {
        return self::log(
            $conn,
            $action,
            $module,
            $target,
            $oldValues,
            $newValues,
            0,
            'guest',
            'Guest',
            $chapelId
        );
    }

    /**
     * Write one audit entry. Silently swallows DB errors so a logging
     * failure never breaks the main request.
     */
    public static function log(
        mysqli $conn,
        string $action,
        string $module,
        string $target,
        ?array $oldValues = null,
        ?array $newValues = null,
        int $userId = 0,
        string $username = 'guest',
        string $role = 'Visitor',
        ?int $chapelId = null,
        ?string $ip = null
    ): bool {
        try {
            $oldJson = !empty($oldValues) ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
            $newJson = !empty($newValues) ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
            $ipAddr  = $ip ?? self::getIp();
            $performedById = ($userId > 0) ? $userId : null;

            $sql = "INSERT INTO audit_logs (action, module, target_description, ip_address, performed_by_id, performed_by_name, performed_by_role, old_values, new_values, chapel_id) 
                    VALUES (?,?,?,?,?,?,?,?,?,?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param(
                'ssssissssi',
                $action,
                $module,
                $target,
                $ipAddr,
                $performedById,
                $username,
                $role,
                $oldJson,
                $newJson,
                $chapelId
            );

            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            // Silently fail logging to prevent application crash
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Audit Logger Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Utility to get the user's IP address, accounting for proxies.
     */
    private static function getIp(): string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return '0.0.0.0';
    }
}