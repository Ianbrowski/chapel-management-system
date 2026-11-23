<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Chat.php';

/**
 * ChatController — JSON API & Dispatcher for Intra-Parish Staff & Clergy Chat
 */
class ChatController extends BaseController {
    private $chatModel;
    private $allowedRoles = ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff'];

    public function __construct($conn, $must_auth = true) {
        parent::__construct($conn, $must_auth);
        $this->chatModel = new Chat($this->conn);
    }

    /**
     * Verify that the current user belongs to the allowed ministry staff & clergy roles.
     */
    public function isAuthorized(): bool {
        return in_array($this->role, $this->allowedRoles, true);
    }

    /**
     * Main AJAX request entry point.
     */
    public function handleAjax(): void {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isAuthorized()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error'   => 'Access denied: Staff and Clergy only.'
            ]);
            exit();
        }

        $action = $_REQUEST['action'] ?? '';

        if (in_array($action, ['get_state', 'get_messages', 'poll', 'unread_count'], true)
            && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (in_array($action, ['send_message', 'start_direct', 'mark_read'], true)) {
            $csrfToken = $_POST['csrf'] ?? ($_POST['csrf_token'] ?? '');
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !$this->validateCsrf($csrfToken)) {
                http_response_code(403);
                echo json_encode([
                    'success'  => false,
                    'error'    => 'Security token expired or invalid request method.',
                    'new_csrf' => $this->generateCsrf()
                ]);
                exit();
            }
        }

        try {
            switch ($action) {
                case 'get_state':
                    $this->ajaxGetState();
                    break;

                case 'get_messages':
                    $this->ajaxGetMessages();
                    break;

                case 'send_message':
                    $this->ajaxSendMessage();
                    break;

                case 'poll':
                    $this->ajaxPoll();
                    break;

                case 'start_direct':
                    $this->ajaxStartDirect();
                    break;

                case 'mark_read':
                    $this->ajaxMarkRead();
                    break;

                case 'unread_count':
                    $this->ajaxUnreadCount();
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Invalid chat action.']);
                    break;
            }
        } catch (Throwable $e) {
            error_log('ChatController error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'An unexpected chat server error occurred. Please try again.'
            ]);
        }
        exit();
    }

    /**
     * Full state snapshot: channels, direct chats, contacts directory, and total unread count.
     */
    private function ajaxGetState(): void {
        $userId   = (int)$this->userId;
        $channels = $this->chatModel->getChannels($userId);
        $directs  = $this->chatModel->getDirectChats($userId);
        $contacts = $this->chatModel->getStaffContacts($userId);
        $unread   = $this->chatModel->getTotalUnreadCount($userId);

        // Fetch current user display info
        $stmt = $this->conn->prepare("SELECT user, full_name, role, profile_pic FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $me = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'current_user' => [
                'id'           => $userId,
                'username'     => $me['user'] ?? '',
                'display_name' => (!empty($me['full_name']) ? $me['full_name'] : ($me['user'] ?? 'User')),
                'role'         => $me['role'] ?? '',
                'profile_pic'  => $me['profile_pic'] ?? null,
                'csrf_token'   => $this->generateCsrf()
            ],
            'channels'     => $channels,
            'directs'      => $directs,
            'contacts'     => $contacts,
            'total_unread' => $unread
        ]);
    }

    /**
     * Get messages for a specific room.
     */
    private function ajaxGetMessages(): void {
        $roomId  = (int)($_GET['room_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        $userId  = (int)$this->userId;

        if ($roomId <= 0 || !$this->chatModel->canAccessRoom($roomId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid room.']);
            return;
        }

        $room     = $this->chatModel->getRoomInfo($roomId, $userId);
        $messages = $this->chatModel->getMessages($roomId, $afterId);

        // Auto mark as read
        if (!empty($messages)) {
            $lastMsg = end($messages);
            $this->chatModel->markAsRead($roomId, $userId, (int)$lastMsg['message_id']);
        } else {
            $this->chatModel->markAsRead($roomId, $userId);
        }

        $totalUnread = $this->chatModel->getTotalUnreadCount($userId);

        echo json_encode([
            'success'      => true,
            'room'         => $room,
            'messages'     => $messages,
            'total_unread' => $totalUnread
        ]);
    }

    /**
     * Send a new message.
     */
    private function ajaxSendMessage(): void {
        $csrfToken = $_POST['csrf'] ?? ($_POST['csrf_token'] ?? '');
        if (!$this->validateCsrf($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success'  => false, 
                'error'    => 'Security token expired. Please refresh the page.',
                'new_csrf' => $this->generateCsrf()
            ]);
            return;
        }

        $roomId = (int)($_POST['room_id'] ?? 0);
        $text   = trim($_POST['message_text'] ?? '');
        $userId = (int)$this->userId;

        if ($roomId <= 0 || $text === '') {
            echo json_encode(['success' => false, 'error' => 'Message text cannot be empty.']);
            return;
        }

        if (!$this->chatModel->canAccessRoom($roomId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized to post in this room.']);
            return;
        }

        $newMsg = $this->chatModel->sendMessage($roomId, $userId, $text);
        if ($newMsg) {
            echo json_encode([
                'success' => true,
                'message' => $newMsg,
                'total_unread' => $this->chatModel->getTotalUnreadCount($userId)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save message.']);
        }
    }

    /**
     * Lightweight poller for new messages in active room + unread count.
     */
    private function ajaxPoll(): void {
        $roomId  = (int)($_GET['room_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        $userId  = (int)$this->userId;

        $newMessages = [];
        if ($roomId > 0 && $this->chatModel->canAccessRoom($roomId, $userId)) {
            $newMessages = $this->chatModel->getMessages($roomId, $afterId);
            if (!empty($newMessages)) {
                $lastMsg = end($newMessages);
                $this->chatModel->markAsRead($roomId, $userId, (int)$lastMsg['message_id']);
            }
        }

        $totalUnread = $this->chatModel->getTotalUnreadCount($userId);

        echo json_encode([
            'success'      => true,
            'room_id'      => $roomId,
            'new_messages' => $newMessages,
            'total_unread' => $totalUnread
        ]);
    }

    /**
     * Open or create a direct chat room with another staff/clergy member.
     */
    private function ajaxStartDirect(): void {
        $targetUserId = (int)($_POST['target_user_id'] ?? ($_GET['target_user_id'] ?? 0));
        $currentUserId = (int)$this->userId;

        if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
            echo json_encode(['success' => false, 'error' => 'Invalid target user.']);
            return;
        }

        // Verify target is staff or clergy
        $stmt = $this->conn->prepare("SELECT id, user, full_name, role, profile_pic FROM users WHERE id = ? AND role IN ('Admin', 'Cathedral Admin', 'Chapel Admin', 'Priest', 'Pastor', 'Staff') LIMIT 1");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $targetUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$targetUser) {
            echo json_encode(['success' => false, 'error' => 'User not eligible for internal chat.']);
            return;
        }

        $roomId = $this->chatModel->getOrCreateDirectRoom($currentUserId, $targetUserId);

        echo json_encode([
            'success' => true,
            'room_id' => $roomId,
            'target_user' => [
                'id'           => (int)$targetUser['id'],
                'display_name' => (!empty($targetUser['full_name']) ? $targetUser['full_name'] : $targetUser['user']),
                'role'         => $targetUser['role'],
                'profile_pic'  => $targetUser['profile_pic']
            ]
        ]);
    }

    /**
     * Mark a room read.
     */
    private function ajaxMarkRead(): void {
        $roomId = (int)($_POST['room_id'] ?? ($_GET['room_id'] ?? 0));
        $userId = (int)$this->userId;
        if ($roomId > 0 && $this->chatModel->canAccessRoom($roomId, $userId)) {
            $this->chatModel->markAsRead($roomId, $userId);
        }
        $totalUnread = $this->chatModel->getTotalUnreadCount($userId);
        echo json_encode(['success' => true, 'total_unread' => $totalUnread]);
    }

    /**
     * Get global unread badge count only.
     */
    private function ajaxUnreadCount(): void {
        $userId = (int)$this->userId;
        $totalUnread = $this->chatModel->getTotalUnreadCount($userId);
        echo json_encode(['success' => true, 'total_unread' => $totalUnread]);
    }
}
