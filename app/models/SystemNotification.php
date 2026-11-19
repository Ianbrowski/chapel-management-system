<?php
/**
 * SystemNotification Model
 * Manages member-specific in-app notifications (requests, approvals, status updates).
 */
class SystemNotification
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create a notification specifically targeted to a user.
     */
    public function create(int $userId, string $title, string $message, string $type = 'event', ?int $referenceId = null): bool
    {
        if ($userId <= 0 || trim($title) === '') {
            return false;
        }

        $stmt = $this->conn->prepare("INSERT INTO system_notifications (user_id, title, message, type, reference_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("isssi", $userId, $title, $message, $type, $referenceId);
        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }

    /**
     * Retrieve notifications for a specific user.
     */
    public function getByUserId(int $userId, int $limit = 15, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("SELECT id, user_id, title, message, type, reference_id, is_read, created_at FROM system_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();

        return $rows;
    }

    /**
     * Count unread notifications for a specific user.
     */
    public function getUnreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) AS c FROM system_notifications WHERE user_id = ? AND is_read = 0");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['c'] ?? 0);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->conn->prepare("UPDATE system_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $id, $userId);
        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->conn->prepare("UPDATE system_notifications SET is_read = 1 WHERE user_id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $userId);
        $res = $stmt->execute();
        $stmt->close();

        return $res;
    }
}
