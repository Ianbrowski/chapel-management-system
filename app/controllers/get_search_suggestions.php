<?php
/**
 * Unified Live Pre-Search Suggestions Endpoint
 *
 * Supplies fast, role-and-chapel-scoped autocomplete suggestions for server-backed
 * dashboard searches (records, events, schedules, priest duties, and tickets).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['suggestions' => []]);
    exit();
}

$scope = trim($_GET['scope'] ?? '');
$q     = trim($_GET['q'] ?? '');
if ($scope === '' || $q === '' || mb_strlen($q) > 80) {
    echo json_encode(['suggestions' => []]);
    exit();
}

require_once __DIR__ . '/../../config/config.php';

$userId          = (int)$_SESSION['user_id'];
$role            = $_SESSION['role'] ?? '';
$sessionChapelId = (int)($_SESSION['chapel_id'] ?? 0);
$isGlobalAdmin   = in_array($role, ['Admin', 'Cathedral Admin'], true);
$isStaffOrClergy = in_array($role, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest'], true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$escaped       = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$prefixPattern = $escaped . '%';
$likePattern   = '%' . $escaped . '%';

$suggestions = [];

function acStatusBadgeStyle(string $status): array {
    $st = strtolower(trim($status));
    if (in_array($st, ['pending', 'open', 'pending_chapel', 'pending_cathedral'], true)) {
        return ['label' => ucfirst(str_replace('_', ' ', $st)), 'color' => '#b45309', 'bg' => '#fef3c7'];
    }
    if (in_array($st, ['confirmed', 'approved', 'in progress', 'in_progress', 'ready'], true)) {
        return ['label' => ucfirst(str_replace('_', ' ', $st)), 'color' => '#047857', 'bg' => '#d1fae5'];
    }
    if (in_array($st, ['completed', 'resolved', 'closed', 'released'], true)) {
        return ['label' => ucfirst(str_replace('_', ' ', $st)), 'color' => '#3f3f46', 'bg' => '#e4e4e7'];
    }
    if (in_array($st, ['rejected', 'cancelled', 'canceled'], true)) {
        return ['label' => ucfirst(str_replace('_', ' ', $st)), 'color' => '#b91c1c', 'bg' => '#fee2e2'];
    }
    return ['label' => $status !== '' ? $status : 'Active', 'color' => '#52525b', 'bg' => '#f4f4f5'];
}

if (in_array($scope, ['records', 'records_cathedral', 'records_chapel'], true)) {
    if (!$isStaffOrClergy) {
        echo json_encode(['suggestions' => []]);
        exit();
    }

    $where  = [
        'sr.is_archived = 0',
        '(sr.person_name LIKE ? OR sr.groom_name LIKE ? OR sr.bride_name LIKE ? OR sr.deceased_name LIKE ? OR sr.child_name LIKE ? OR sr.father_name LIKE ? OR sr.mother_name LIKE ? OR sr.reference_id LIKE ?)',
    ];
    $params = array_fill(0, 8, $likePattern);
    $types  = str_repeat('s', 8);

    if ($scope === 'records_cathedral') {
        if (!$isGlobalAdmin) {
            if ($role === 'Chapel Admin' || $sessionChapelId > 0) {
                echo json_encode(['suggestions' => []]);
                exit();
            }
            $where[] = "(sr.scope = 'cathedral' OR sr.chapel_id IS NULL OR sr.chapel_id = 0)";
        } elseif (isset($_GET['chapel_id']) && $_GET['chapel_id'] !== '') {
            $reqCid = (int)$_GET['chapel_id'];
            if ($reqCid === 0) {
                $where[] = "(sr.scope = 'cathedral' OR sr.chapel_id IS NULL OR sr.chapel_id = 0)";
            } elseif ($reqCid > 0) {
                $where[]  = 'sr.chapel_id = ?';
                $params[] = $reqCid;
                $types   .= 'i';
            }
        }
    } elseif ($scope === 'records_chapel') {
        $reqChapelId = (int)($_GET['chapel_id'] ?? 0);
        if (!$isGlobalAdmin) {
            if ($sessionChapelId > 0) {
                $where[]  = 'sr.chapel_id = ?';
                $params[] = $sessionChapelId;
                $types   .= 'i';
            } else {
                echo json_encode(['suggestions' => []]);
                exit();
            }
        } elseif ($reqChapelId > 0) {
            $where[]  = 'sr.chapel_id = ?';
            $params[] = $reqChapelId;
            $types   .= 'i';
        } else {
            $where[] = "(sr.scope = 'chapel' OR (sr.chapel_id IS NOT NULL AND sr.chapel_id > 0))";
        }
    } elseif (!$isGlobalAdmin) {
        if ($sessionChapelId > 0) {
            $where[]  = 'sr.chapel_id = ?';
            $params[] = $sessionChapelId;
            $types   .= 'i';
        } elseif ($role === 'Chapel Admin') {
            $where[] = '1 = 0';
        } else {
            $where[] = "(sr.scope = 'cathedral' OR sr.chapel_id IS NULL OR sr.chapel_id = 0)";
        }
    }

    $recType = trim($_GET['record_type'] ?? '');
    if ($recType !== '') {
        $where[]  = 'sr.record_type = ?';
        $params[] = $recType;
        $types   .= 's';
    }

    $sql = "SELECT sr.record_id, sr.reference_id, sr.record_type,
                   COALESCE(NULLIF(TRIM(sr.person_name), ''), NULLIF(TRIM(sr.child_name), ''), NULLIF(TRIM(sr.deceased_name), ''), NULLIF(TRIM(sr.groom_name), ''), '') AS display_name,
                   sr.groom_name, sr.bride_name, sr.father_name, sr.mother_name,
                   YEAR(sr.event_date) AS event_year,
                   COALESCE(c.chapel_name, 'Cathedral') AS chapel_name
            FROM sacramental_records sr
            LEFT JOIN chapels c ON c.chapel_id = sr.chapel_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE WHEN sr.person_name LIKE ? OR sr.reference_id LIKE ? THEN 0 ELSE 1 END,
                     sr.event_date DESC, sr.record_id DESC
            LIMIT 8";

    $params[] = $prefixPattern;
    $params[] = $prefixPattern;
    $types   .= 'ss';

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)$row['display_name']);
                if ($name === '' && ($row['record_type'] ?? '') === 'Wedding') {
                    $name = trim(($row['groom_name'] ?? '') . ' & ' . ($row['bride_name'] ?? ''), ' &');
                }
                if ($name === '') {
                    $name = $row['reference_id'] ?? '';
                }
                $meta = [];
                if (!empty($row['record_type'])) $meta[] = $row['record_type'];
                if (!empty($row['event_year']))  $meta[] = (string)$row['event_year'];
                if (!empty($row['chapel_name'])) $meta[] = $row['chapel_name'];

                $suggestions[] = [
                    'label'      => $name,
                    'value'      => $name,
                    'sub'        => implode(' · ', $meta),
                    'badge'      => $row['reference_id'] ?? '',
                    'badgeColor' => '#52525b',
                    'badgeBg'    => '#f4f4f5',
                ];
            }
        }
        $stmt->close();
    }
} elseif (in_array($scope, ['events', 'my_events', 'calendar_events', 'cathedral_events', 'chapel_events', 'priest_duties'], true)) {
    $where  = [
        'e.is_archived = 0',
        '(e.service_id IS NULL OR e.service_id != 8)',
        '(e.title LIKE ? OR e.event_type LIKE ? OR COALESCE(u.full_name, u.user, \'\') LIKE ? OR COALESCE(e.pastor_assigned, \'\') LIKE ? OR COALESCE(c.chapel_name, \'\') LIKE ?)',
    ];
    $params = array_fill(0, 5, $likePattern);
    $types  = str_repeat('s', 5);

    if ($scope === 'my_events') {
        $where[]  = 'e.requested_by = ?';
        $params[] = $userId;
        $types   .= 'i';
    } elseif ($scope === 'events' || $scope === 'priest_duties') {
        if (!$isStaffOrClergy) {
            $where[]  = 'e.requested_by = ?';
            $params[] = $userId;
            $types   .= 'i';
        } elseif (!$isGlobalAdmin) {
            if ($sessionChapelId > 0) {
                $where[]  = 'e.chapel_id = ?';
                $params[] = $sessionChapelId;
                $types   .= 'i';
            } elseif ($role === 'Chapel Admin') {
                $where[] = '1 = 0';
            } elseif ($role === 'Staff') {
                $where[] = '(e.chapel_id IS NULL OR e.chapel_id = 0)';
            }
        }
        if ($scope === 'priest_duties') {
            $where[] = "(e.pastor_id IS NOT NULL OR (e.pastor_assigned IS NOT NULL AND e.pastor_assigned != ''))";
        }
    } elseif ($scope === 'calendar_events') {
        if (!$isStaffOrClergy) {
            $where[]  = "(e.status IN ('Confirmed', 'Completed') OR e.requested_by = ?)";
            $params[] = $userId;
            $types   .= 'i';
        } elseif (!$isGlobalAdmin && $sessionChapelId > 0) {
            $where[]  = '(e.chapel_id = ? OR e.chapel_id IS NULL OR e.chapel_id = 0)';
            $params[] = $sessionChapelId;
            $types   .= 'i';
        }
    } elseif ($scope === 'cathedral_events') {
        if (!$isStaffOrClergy || (!$isGlobalAdmin && ($role === 'Chapel Admin' || $sessionChapelId > 0))) {
            echo json_encode(['suggestions' => []]);
            exit();
        }
        $where[] = '(e.chapel_id IS NULL OR e.chapel_id = 0)';
    } elseif ($scope === 'chapel_events') {
        if (!$isStaffOrClergy) {
            echo json_encode(['suggestions' => []]);
            exit();
        }
        $where[] = '(e.chapel_id IS NOT NULL AND e.chapel_id > 0)';
        if (!$isGlobalAdmin) {
            if ($sessionChapelId > 0) {
                $where[]  = 'e.chapel_id = ?';
                $params[] = $sessionChapelId;
                $types   .= 'i';
            } elseif ($role === 'Chapel Admin' || $role === 'Staff') {
                echo json_encode(['suggestions' => []]);
                exit();
            }
        }
    }

    $sql = "SELECT e.event_id, e.title, e.event_type, e.start_time, e.status, e.pastor_assigned,
                   COALESCE(u.full_name, u.user, '') AS requester_name,
                   COALESCE(c.chapel_name, 'Cathedral') AS venue_name
            FROM events e
            LEFT JOIN users u ON u.id = e.requested_by
            LEFT JOIN chapels c ON c.chapel_id = e.chapel_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY CASE WHEN e.title LIKE ? OR COALESCE(u.full_name, u.user, '') LIKE ? THEN 0 ELSE 1 END,
                     e.start_time DESC, e.event_id DESC
            LIMIT 8";

    $params[] = $prefixPattern;
    $params[] = $prefixPattern;
    $types   .= 'ss';

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $title = trim((string)($row['title'] ?? ''));
                if ($title === '') continue;

                $badge = acStatusBadgeStyle((string)($row['status'] ?? ''));
                $meta  = [];
                if (!empty($row['event_type'])) $meta[] = $row['event_type'];
                if (!empty($row['start_time'])) $meta[] = date('M d, Y', strtotime($row['start_time']));
                if ($scope === 'priest_duties' && !empty($row['pastor_assigned'])) {
                    $meta[] = $row['pastor_assigned'];
                } elseif (!empty($row['requester_name']) && stripos($title, $row['requester_name']) === false) {
                    $meta[] = $row['requester_name'];
                } elseif (!empty($row['venue_name'])) {
                    $meta[] = $row['venue_name'];
                }

                // Choose best fill value: if search matched priest/requester instead of title, preserve whichever matched closely
                $fillVal = $title;
                if ($scope === 'priest_duties' && !empty($row['pastor_assigned']) && stripos($row['pastor_assigned'], $q) !== false && stripos($title, $q) === false) {
                    $fillVal = $row['pastor_assigned'];
                }

                $suggestions[] = [
                    'label'      => $title,
                    'value'      => $fillVal,
                    'sub'        => implode(' · ', $meta),
                    'badge'      => $badge['label'],
                    'badgeColor' => $badge['color'],
                    'badgeBg'    => $badge['bg'],
                ];
            }
        }
        $stmt->close();
    }
}

echo json_encode(['suggestions' => $suggestions]);
