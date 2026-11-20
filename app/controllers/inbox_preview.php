<?php
/**
 * Real-time Inbox Preview Dispatcher (Facebook-style Inbox Dropdown)
 * Returns JSON feed of recent inquiries/messages tailored by role and chapel scope.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Inquiry.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/BaseController.php';

if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime(?string $datetime): string {
        if (empty($datetime)) return '';
        $timestamp = strtotime($datetime);
        if (!$timestamp) return '';

        $diff = time() - $timestamp;
        if ($diff < 0) return 'Just now';
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 172800) return 'Yesterday';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', $timestamp);
    }
}

if (!function_exists('getInquiryVisuals')) {
    function getInquiryVisuals(string $type, string $category): array {
        $t = strtolower(trim($type));
        $c = strtolower(trim($category));

        if (strpos($t, 'cert') !== false || strpos($c, 'cert') !== false) {
            return ['icon' => 'fa-file-invoice', 'class' => 'inbox-icon-cert'];
        }
        if (strpos($t, 'merch') !== false || strpos($c, 'merch') !== false) {
            return ['icon' => 'fa-bag-shopping', 'class' => 'inbox-icon-merch'];
        }
        if (strpos($t, 'event') !== false || strpos($c, 'event') !== false) {
            return ['icon' => 'fa-calendar-day', 'class' => 'inbox-icon-event'];
        }
        if (strpos($c, 'baptism') !== false || strpos($c, 'wedding') !== false || strpos($c, 'mass') !== false || strpos($c, 'funeral') !== false) {
            return ['icon' => 'fa-hands-praying', 'class' => 'inbox-icon-sacrament'];
        }
        return ['icon' => 'fa-circle-question', 'class' => 'inbox-icon-general'];
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'success'       => false,
        'error'         => 'Unauthenticated',
        'pending_count' => 0,
        'total'         => 0,
        'items'         => []
    ]);
    exit();
}

try {
    $base = new BaseController($conn, true);
    $perms = $base->getPermissions();
    $role = $base->getRole();
    $chapelId = $base->getChapelId();
    $userId = (int)$_SESSION['user_id'];

    $isMember = ($role === 'Member');
    $canManageInquiries = $perms['can_manage_inquiries'] ?? false;

    if (!$canManageInquiries && !$isMember) {
        header('Content-Type: application/json', true, 403);
        echo json_encode([
            'success'       => false,
            'error'         => 'Forbidden',
            'pending_count' => 0,
            'total'         => 0,
            'items'         => []
        ]);
        exit();
    }

    // Action: Mark notification as read (POST + CSRF required)
    if (($_REQUEST['action'] ?? '') === 'mark_read') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Content-Type: application/json', true, 405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit();
        }
        $csrfToken = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!$base->validateCsrf($csrfToken)) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token']);
            exit();
        }
        $notifId = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
        if ($isMember) {
            require_once __DIR__ . '/../models/SystemNotification.php';
            $notifModel = new SystemNotification($conn);
            if ($notifId > 0) {
                $notifModel->markAsRead($notifId, $userId);
            } else {
                $notifModel->markAllAsRead($userId);
            }
            $remainingUnread = $notifModel->getUnreadCount($userId);
        } else {
            if (!isset($_SESSION['inbox_seen_ids']) || !is_array($_SESSION['inbox_seen_ids'])) {
                $_SESSION['inbox_seen_ids'] = [];
            }
            if ($notifId > 0) {
                if (!in_array($notifId, $_SESSION['inbox_seen_ids'], true)) {
                    $_SESSION['inbox_seen_ids'][] = $notifId;
                }
            } else {
                $idsRaw = $_POST['ids'] ?? '';
                if ($idsRaw !== '') {
                    foreach (explode(',', (string)$idsRaw) as $rawId) {
                        $iid = (int)trim($rawId);
                        if ($iid > 0 && !in_array($iid, $_SESSION['inbox_seen_ids'], true)) {
                            $_SESSION['inbox_seen_ids'][] = $iid;
                        }
                    }
                } else {
                    $chapelFilterTmp = in_array($role, ['Admin', 'Cathedral Admin'], true)
                        ? null
                        : (!empty($chapelId) ? (int)$chapelId : 'cathedral');
                    $inqTmp = new Inquiry($conn);
                    $resTmp = $inqTmp->getAll(['chapel_id' => $chapelFilterTmp, 'sort' => 'date_desc'], 1, 25);
                    foreach (($resTmp['records'] ?? []) as $rowTmp) {
                        $iid = (int)$rowTmp['inquiry_id'];
                        if ($iid > 0 && !in_array($iid, $_SESSION['inbox_seen_ids'], true)) {
                            $_SESSION['inbox_seen_ids'][] = $iid;
                        }
                    }
                }
            }
            $remainingUnread = null;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'unread_count' => $remainingUnread]);
        exit();
    }

    $inquiryModel = new Inquiry($conn);
    $records = [];
    $pendingCount = 0;

    if ($isMember) {
        require_once __DIR__ . '/../models/SystemNotification.php';
        $notifModel = new SystemNotification($conn);
        $notifications = $notifModel->getByUserId($userId, 15);
        $pendingCount = $notifModel->getUnreadCount($userId);

        $items = [];
        foreach ($notifications as $n) {
            $type = strtolower($n['type'] ?? 'event');
            $icon = 'fa-bell';
            $iconClass = 'inbox-icon-event';
            if (strpos($type, 'sacrament') !== false || strpos($type, 'baptism') !== false) {
                $icon = 'fa-hands-praying';
                $iconClass = 'inbox-icon-sacrament';
            } elseif (strpos($type, 'approved') !== false) {
                $icon = 'fa-circle-check';
                $iconClass = 'inbox-icon-cert';
            } elseif (strpos($type, 'rejected') !== false) {
                $icon = 'fa-circle-xmark';
                $iconClass = 'inbox-icon-general';
            }

            $isUnread = empty($n['is_read']);
            $items[] = [
                'id'           => (int)$n['id'],
                'name'         => 'Cathedral Notice',
                'email'        => '',
                'request_type' => htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'),
                'category'     => ucfirst($type),
                'chapel_name'  => 'Mother Cathedral',
                'snippet'      => htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'),
                'status'       => $isUnread ? 'New' : 'Read',
                'is_pending'   => $isUnread,
                'is_unread'    => $isUnread,
                'created_at'   => $n['created_at'] ?? '',
                'time_ago'     => formatRelativeTime($n['created_at'] ?? ''),
                'icon'         => $icon,
                'icon_class'   => $iconClass,
                'target_url'   => 'dashboard.php?section=events'
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success'       => true,
            'is_member'     => true,
            'total'         => count($items),
            'pending_count' => (int)$pendingCount,
            'unread_count'  => (int)$pendingCount,
            'items'         => $items,
            'timestamp'     => date('H:i:s')
        ]);
        exit();
    } else {
        if (in_array($role, ['Admin', 'Cathedral Admin'], true)) {
            $chapelFilter = null;
        } elseif (!empty($chapelId)) {
            $chapelFilter = (int)$chapelId;
        } else {
            $chapelFilter = 'cathedral';
        }
        $limit = 10;
        $filters = [
            'chapel_id' => $chapelFilter,
            'sort'      => 'date_desc'
        ];
        $res = $inquiryModel->getAll($filters, 1, $limit);
        $records = $res['records'] ?? [];
        $pendingCount = $inquiryModel->getPendingCount($chapelFilter);
    }

    $seenIds = isset($_SESSION['inbox_seen_ids']) && is_array($_SESSION['inbox_seen_ids']) ? $_SESSION['inbox_seen_ids'] : [];
    $items = [];
    $unreadCount = 0;
    foreach ($records as $row) {
        $visuals = getInquiryVisuals((string)($row['request_type'] ?? ''), (string)($row['service_category'] ?? ''));
        $status = $row['status'] ?: 'Pending';
        $isPending = ($status === 'Pending');
        $inqId = (int)$row['inquiry_id'];
        $isUnread = $isPending && !in_array($inqId, $seenIds, true);
        if ($isUnread) {
            $unreadCount++;
        }

        $rawDetails = strip_tags((string)($row['details'] ?? ''));
        $snippet = mb_strimwidth($rawDetails, 0, 75, '...');
        if (empty($snippet)) {
            $snippet = 'No additional notes provided.';
        }

        $items[] = [
            'id'           => $inqId,
            'name'         => htmlspecialchars($row['full_name'] ?? 'Parishioner', ENT_QUOTES, 'UTF-8'),
            'email'        => htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'),
            'request_type' => htmlspecialchars($row['request_type'] ?? 'General Inquiry', ENT_QUOTES, 'UTF-8'),
            'category'     => htmlspecialchars($row['service_category'] ?? 'Inquiry', ENT_QUOTES, 'UTF-8'),
            'chapel_name'  => htmlspecialchars($row['chapel_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'snippet'      => htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8'),
            'status'       => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
            'is_pending'   => $isPending,
            'is_unread'    => $isUnread,
            'created_at'   => $row['created_at'] ?? '',
            'time_ago'     => formatRelativeTime($row['created_at'] ?? ''),
            'icon'         => $visuals['icon'],
            'icon_class'   => $visuals['class'],
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success'       => true,
        'is_member'     => false,
        'total'         => count($items),
        'pending_count' => (int)$pendingCount,
        'unread_count'  => (int)$unreadCount,
        'items'         => $items,
        'timestamp'     => date('H:i:s')
    ]);
} catch (Throwable $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'success'       => false,
        'error'         => 'Server error',
        'pending_count' => 0,
        'total'         => 0,
        'items'         => []
    ]);
}
