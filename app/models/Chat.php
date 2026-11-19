<?php
/**
 * Chat Model — Intra-Parish Staff & Clergy Communication Engine
 * Manages ministry channels, direct messaging, message dispatch, and read receipts.
 */
class Chat {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->ensureSchema();
    }

    /**
     * Self-migrating schema setup: guarantees tables and default ministry channels exist.
     */
    public function ensureSchema(): void {
        static $initialized = false;
        if ($initialized) return;
        $initialized = true;

        try {
            // 1. Chat Rooms / Channels
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS `parish_chat_rooms` (
                    `room_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `room_type` ENUM('channel', 'direct') NOT NULL DEFAULT 'channel',
                    `name` VARCHAR(120) NOT NULL,
                    `description` VARCHAR(255) DEFAULT NULL,
                    `icon` VARCHAR(50) DEFAULT 'fa-comments',
                    `chapel_id` INT DEFAULT NULL,
                    `created_by` INT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_room_type` (`room_type`),
                    INDEX `idx_chapel` (`chapel_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 2. Chat Participants (tracks unread position and memberships)
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS `parish_chat_participants` (
                    `room_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `last_read_message_id` INT DEFAULT 0,
                    `last_read_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `is_muted` TINYINT(1) DEFAULT 0,
                    `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`room_id`, `user_id`),
                    INDEX `idx_user` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 3. Chat Messages
            $this->conn->query("
                CREATE TABLE IF NOT EXISTS `parish_chat_messages` (
                    `message_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `room_id` INT NOT NULL,
                    `sender_id` INT NOT NULL,
                    `message_text` TEXT NOT NULL,
                    `attachment_path` VARCHAR(255) DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_room_msg` (`room_id`, `message_id`),
                    INDEX `idx_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Seed default channels if none exist
            $res = $this->conn->query("SELECT COUNT(*) AS total FROM `parish_chat_rooms` WHERE `room_type` = 'channel'");
            $row = $res ? $res->fetch_assoc() : null;
            if (!$row || (int)$row['total'] === 0) {
                $defaultChannels = [
                    [
                        'name' => 'General Ministry',
                        'desc' => 'Parish-wide staff and clergy general coordination & announcements',
                        'icon' => 'fa-bullhorn'
                    ],
                    [
                        'name' => 'Liturgical & Sacraments',
                        'desc' => 'Mass schedule planning, sacramental inquiries, altar server & priest duty',
                        'icon' => 'fa-cross'
                    ],
                    [
                        'name' => 'Chapel Coordinators',
                        'desc' => 'Satellite chapel logistics, maintenance requests, and event updates',
                        'icon' => 'fa-place-of-worship'
                    ]
                ];

                $stmt = $this->conn->prepare("INSERT INTO `parish_chat_rooms` (`room_type`, `name`, `description`, `icon`, `created_by`) VALUES ('channel', ?, ?, ?, 1)");
                if ($stmt) {
                    foreach ($defaultChannels as $ch) {
                        $stmt->bind_param('sss', $ch['name'], $ch['desc'], $ch['icon']);
                        $stmt->execute();
                    }
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {
            error_log("ParishChat ensureSchema error: " . $e->getMessage());
        }
    }

    /**
     * Retrieve all active ministry channels and compute user-specific unread count.
     */
    public function getChannels(int $userId): array {
        $sql = "
            SELECT 
                r.room_id,
                r.room_type,
                r.name,
                r.description,
                r.icon,
                r.created_at,
                COALESCE(p.last_read_message_id, 0) AS last_read_message_id,
                (
                    SELECT COUNT(*) 
                    FROM parish_chat_messages m 
                    WHERE m.room_id = r.room_id 
                      AND m.message_id > COALESCE(p.last_read_message_id, 0)
                      AND m.sender_id != ?
                ) AS unread_count,
                (
                    SELECT m2.message_text 
                    FROM parish_chat_messages m2 
                    WHERE m2.room_id = r.room_id 
                    ORDER BY m2.message_id DESC 
                    LIMIT 1
                ) AS last_message_text,
                (
                    SELECT m2.created_at 
                    FROM parish_chat_messages m2 
                    WHERE m2.room_id = r.room_id 
                    ORDER BY m2.message_id DESC 
                    LIMIT 1
                ) AS last_message_time
            FROM parish_chat_rooms r
            LEFT JOIN parish_chat_participants p 
                   ON p.room_id = r.room_id AND p.user_id = ?
            WHERE r.room_type = 'channel'
            ORDER BY r.room_id ASC
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $channels = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $channels;
    }

    /**
     * Retrieve active direct conversations for a user.
     */
    public function getDirectChats(int $userId): array {
        $sql = "
            SELECT 
                r.room_id,
                r.room_type,
                u.id AS other_user_id,
                COALESCE(NULLIF(u.full_name, ''), u.user) AS other_user_name,
                u.user AS other_username,
                u.role AS other_user_role,
                u.profile_pic AS other_profile_pic,
                COALESCE(my_p.last_read_message_id, 0) AS last_read_message_id,
                (
                    SELECT COUNT(*) 
                    FROM parish_chat_messages m 
                    WHERE m.room_id = r.room_id 
                      AND m.message_id > COALESCE(my_p.last_read_message_id, 0)
                      AND m.sender_id != ?
                ) AS unread_count,
                (
                    SELECT m2.message_text 
                    FROM parish_chat_messages m2 
                    WHERE m2.room_id = r.room_id 
                    ORDER BY m2.message_id DESC 
                    LIMIT 1
                ) AS last_message_text,
                (
                    SELECT m2.created_at 
                    FROM parish_chat_messages m2 
                    WHERE m2.room_id = r.room_id 
                    ORDER BY m2.message_id DESC 
                    LIMIT 1
                ) AS last_message_time
            FROM parish_chat_rooms r
            JOIN parish_chat_participants my_p 
              ON my_p.room_id = r.room_id AND my_p.user_id = ?
            JOIN parish_chat_participants other_p 
              ON other_p.room_id = r.room_id AND other_p.user_id != ?
            JOIN users u 
              ON u.id = other_p.user_id
            WHERE r.room_type = 'direct'
            ORDER BY COALESCE(last_message_time, r.created_at) DESC
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('iii', $userId, $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $directs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $directs;
    }

    /**
     * Get all internal staff & clergy members that the current user can initiate a chat with.
     */
    public function getStaffContacts(int $excludeUserId): array {
        $sql = "
            SELECT 
                u.id, 
                u.user, 
                COALESCE(NULLIF(u.full_name, ''), u.user) AS display_name,
                u.role, 
                u.profile_pic, 
                c.chapel_name
            FROM users u
            LEFT JOIN chapels c ON u.chapel_id = c.chapel_id
            WHERE u.id != ?
              AND u.role IN ('Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff')
              AND (u.status = 'active' OR u.status IS NULL)
            ORDER BY 
                CASE 
                    WHEN u.role IN ('Admin', 'Cathedral Admin') THEN 1
                    WHEN u.role IN ('Priest', 'Pastor') THEN 2
                    WHEN u.role = 'Chapel Admin' THEN 3
                    ELSE 4
                END,
                display_name ASC
        ";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $excludeUserId);
        $stmt->execute();
        $res = $stmt->get_result();
        $contacts = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $contacts;
    }

    /**
     * Find existing direct chat room between two users, or create a new one.
     */
    public function getOrCreateDirectRoom(int $user1Id, int $user2Id): int {
        // Look for an existing direct room with both users
        $sql = "
            SELECT r.room_id
            FROM parish_chat_rooms r
            JOIN parish_chat_participants p1 ON r.room_id = p1.room_id AND p1.user_id = ?
            JOIN parish_chat_participants p2 ON r.room_id = p2.room_id AND p2.user_id = ?
            WHERE r.room_type = 'direct'
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $user1Id, $user2Id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return (int)$row['room_id'];
            }
            $stmt->close();
        }

        // Create new direct room
        $createStmt = $this->conn->prepare("INSERT INTO parish_chat_rooms (room_type, name, description, created_by) VALUES ('direct', 'Direct Message', 'One-on-one direct message', ?)");
        if (!$createStmt) return 0;
        $createStmt->bind_param('i', $user1Id);
        $createStmt->execute();
        $roomId = (int)$this->conn->insert_id;
        $createStmt->close();

        if ($roomId > 0) {
            $partStmt = $this->conn->prepare("INSERT IGNORE INTO parish_chat_participants (room_id, user_id) VALUES (?, ?), (?, ?)");
            if ($partStmt) {
                $partStmt->bind_param('iiii', $roomId, $user1Id, $roomId, $user2Id);
                $partStmt->execute();
                $partStmt->close();
            }
        }

        return $roomId;
    }

    /**
     * Check if a user has access to a given room.
     */
    public function canAccessRoom(int $roomId, int $userId): bool {
        $stmt = $this->conn->prepare("SELECT room_type FROM parish_chat_rooms WHERE room_id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $roomId);
        $stmt->execute();
        $res = $stmt->get_result();
        $room = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$room) return false;

        // Channels are open to all authenticated staff & clergy
        if ($room['room_type'] === 'channel') {
            return true;
        }

        // Direct messages require participation
        $pStmt = $this->conn->prepare("SELECT 1 FROM parish_chat_participants WHERE room_id = ? AND user_id = ? LIMIT 1");
        if (!$pStmt) return false;
        $pStmt->bind_param('ii', $roomId, $userId);
        $pStmt->execute();
        $pRes = $pStmt->get_result();
        $isMember = ($pRes && $pRes->num_rows > 0);
        $pStmt->close();

        return $isMember;
    }

    /**
     * Fetch messages for a room, optionally after a specific message_id for polling.
     */
    public function getMessages(int $roomId, int $afterMessageId = 0, int $limit = 60): array {
        if ($afterMessageId > 0) {
            $sql = "
                SELECT 
                    m.message_id,
                    m.room_id,
                    m.sender_id,
                    m.message_text,
                    m.attachment_path,
                    m.created_at,
                    u.user AS sender_username,
                    COALESCE(NULLIF(u.full_name, ''), u.user) AS sender_name,
                    u.role AS sender_role,
                    u.profile_pic AS sender_profile_pic
                FROM parish_chat_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.room_id = ? AND m.message_id > ?
                ORDER BY m.message_id ASC
                LIMIT ?
            ";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return [];
            $stmt->bind_param('iii', $roomId, $afterMessageId, $limit);
        } else {
            $sql = "
                SELECT * FROM (
                    SELECT 
                        m.message_id,
                        m.room_id,
                        m.sender_id,
                        m.message_text,
                        m.attachment_path,
                        m.created_at,
                        u.user AS sender_username,
                        COALESCE(NULLIF(u.full_name, ''), u.user) AS sender_name,
                        u.role AS sender_role,
                        u.profile_pic AS sender_profile_pic
                    FROM parish_chat_messages m
                    JOIN users u ON m.sender_id = u.id
                    WHERE m.room_id = ?
                    ORDER BY m.message_id DESC
                    LIMIT ?
                ) sub ORDER BY sub.message_id ASC
            ";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) return [];
            $stmt->bind_param('ii', $roomId, $limit);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $messages = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $messages;
    }

    /**
     * Send a new message to a room.
     */
    public function sendMessage(int $roomId, int $senderId, string $text, ?string $attachment = null): ?array {
        $trimmed = trim($text);
        if ($trimmed === '' && empty($attachment)) {
            return null;
        }

        // Auto-enroll sender in participants if not present
        $pStmt = $this->conn->prepare("INSERT IGNORE INTO parish_chat_participants (room_id, user_id) VALUES (?, ?)");
        if ($pStmt) {
            $pStmt->bind_param('ii', $roomId, $senderId);
            $pStmt->execute();
            $pStmt->close();
        }

        $stmt = $this->conn->prepare("INSERT INTO parish_chat_messages (room_id, sender_id, message_text, attachment_path) VALUES (?, ?, ?, ?)");
        if (!$stmt) return null;
        $stmt->bind_param('iiss', $roomId, $senderId, $trimmed, $attachment);
        $ok = $stmt->execute();
        $messageId = $ok ? (int)$this->conn->insert_id : 0;
        $stmt->close();

        if ($messageId > 0) {
            // Update sender's last read message id to self
            $this->markAsRead($roomId, $senderId, $messageId);

            // Return full message object for immediate UI insertion
            $uStmt = $this->conn->prepare("SELECT user, full_name, role, profile_pic FROM users WHERE id = ?");
            $uStmt->bind_param('i', $senderId);
            $uStmt->execute();
            $u = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            return [
                'message_id' => $messageId,
                'room_id' => $roomId,
                'sender_id' => $senderId,
                'message_text' => $trimmed,
                'attachment_path' => $attachment,
                'created_at' => date('Y-m-d H:i:s'),
                'sender_username' => $u['user'] ?? '',
                'sender_name' => (!empty($u['full_name']) ? $u['full_name'] : ($u['user'] ?? 'User')),
                'sender_role' => $u['role'] ?? '',
                'sender_profile_pic' => $u['profile_pic'] ?? null
            ];
        }

        return null;
    }

    /**
     * Update participant's last read position.
     */
    public function markAsRead(int $roomId, int $userId, ?int $upToMessageId = null): void {
        if ($upToMessageId === null) {
            $maxStmt = $this->conn->prepare("SELECT COALESCE(MAX(message_id), 0) AS max_id FROM parish_chat_messages WHERE room_id = ?");
            if ($maxStmt) {
                $maxStmt->bind_param('i', $roomId);
                $maxStmt->execute();
                $maxRow = $maxStmt->get_result()->fetch_assoc();
                $upToMessageId = (int)($maxRow['max_id'] ?? 0);
                $maxStmt->close();
            } else {
                $upToMessageId = 0;
            }
        }

        $stmt = $this->conn->prepare("
            INSERT INTO parish_chat_participants (room_id, user_id, last_read_message_id, last_read_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)),
                last_read_at = NOW()
        ");
        if ($stmt) {
            $stmt->bind_param('iii', $roomId, $userId, $upToMessageId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Calculate total unread messages count across all accessible channels and direct messages for a user.
     */
    public function getTotalUnreadCount(int $userId): int {
        // 1. Unread in channels
        $sqlChannels = "
            SELECT COUNT(*) AS total
            FROM parish_chat_messages m
            JOIN parish_chat_rooms r ON m.room_id = r.room_id
            LEFT JOIN parish_chat_participants p ON p.room_id = r.room_id AND p.user_id = ?
            WHERE r.room_type = 'channel'
              AND m.message_id > COALESCE(p.last_read_message_id, 0)
              AND m.sender_id != ?
        ";
        $stmtC = $this->conn->prepare($sqlChannels);
        if (!$stmtC) return 0;
        $stmtC->bind_param('ii', $userId, $userId);
        $stmtC->execute();
        $chRow = $stmtC->get_result()->fetch_assoc();
        $channelUnread = (int)($chRow['total'] ?? 0);
        $stmtC->close();

        // 2. Unread in direct chats
        $sqlDirect = "
            SELECT COUNT(*) AS total
            FROM parish_chat_messages m
            JOIN parish_chat_rooms r ON m.room_id = r.room_id
            JOIN parish_chat_participants my_p ON my_p.room_id = r.room_id AND my_p.user_id = ?
            WHERE r.room_type = 'direct'
              AND m.message_id > COALESCE(my_p.last_read_message_id, 0)
              AND m.sender_id != ?
        ";
        $stmtD = $this->conn->prepare($sqlDirect);
        if (!$stmtD) return $channelUnread;
        $stmtD->bind_param('ii', $userId, $userId);
        $stmtD->execute();
        $dRow = $stmtD->get_result()->fetch_assoc();
        $directUnread = (int)($dRow['total'] ?? 0);
        $stmtD->close();

        return $channelUnread + $directUnread;
    }

    /**
     * Get room meta information.
     */
    public function getRoomInfo(int $roomId, int $currentUserId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM parish_chat_rooms WHERE room_id = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $roomId);
        $stmt->execute();
        $res = $stmt->get_result();
        $room = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$room) return null;

        if ($room['room_type'] === 'direct') {
            // Find the other user
            $oStmt = $this->conn->prepare("
                SELECT u.id, u.user, COALESCE(NULLIF(u.full_name, ''), u.user) AS display_name, u.role, u.profile_pic
                FROM parish_chat_participants p
                JOIN users u ON p.user_id = u.id
                WHERE p.room_id = ? AND p.user_id != ?
                LIMIT 1
            ");
            if ($oStmt) {
                $oStmt->bind_param('ii', $roomId, $currentUserId);
                $oStmt->execute();
                $otherUser = $oStmt->get_result()->fetch_assoc();
                $oStmt->close();
                if ($otherUser) {
                    $room['display_name'] = $otherUser['display_name'];
                    $room['other_user'] = $otherUser;
                }
            }
        } else {
            $room['display_name'] = $room['name'];
        }

        return $room;
    }
}
