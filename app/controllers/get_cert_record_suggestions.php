<?php
/**
 * AJAX Autocomplete Endpoint for Certificate Archive Record Suggestions
 *
 * Returns matching canonical names and record metadata from sacramental_records
 * for authorized Parish Staff / Admin users as they type.
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['suggestions' => []]);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$allowedRoles = ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest'];
if (!in_array($role, $allowedRoles, true)) {
    echo json_encode(['suggestions' => []]);
    exit();
}

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) > 80) {
    echo json_encode(['suggestions' => []]);
    exit();
}

require_once __DIR__ . '/../../config/config.php';

$escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$prefixPattern = $escaped . '%';
$likePattern   = '%' . $escaped . '%';

$where  = [
    'sr.is_archived = 0',
    '(sr.person_name LIKE ? OR sr.groom_name LIKE ? OR sr.bride_name LIKE ? OR sr.deceased_name LIKE ? OR sr.child_name LIKE ? OR sr.reference_id LIKE ?)',
];
$params = [$likePattern, $likePattern, $likePattern, $likePattern, $likePattern, $likePattern];
$types  = 'ssssss';

$isGlobalAdmin = in_array($role, ['Admin', 'Cathedral Admin'], true);
$sessionChapelId = (int)($_SESSION['chapel_id'] ?? 0);
if (!$isGlobalAdmin) {
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

$recordTypeHint = trim($_GET['record_type'] ?? '');
$orderPrefix = 'CASE WHEN sr.person_name LIKE ? THEN 0 ELSE 1 END';
$orderParams = [$prefixPattern];
$orderTypes  = 's';

if ($recordTypeHint !== '') {
    $orderPrefix .= ', CASE WHEN sr.record_type = ? THEN 0 ELSE 1 END';
    $orderParams[] = $recordTypeHint;
    $orderTypes   .= 's';
}

$sql = "SELECT
            sr.record_id,
            sr.reference_id,
            sr.record_type,
            COALESCE(NULLIF(TRIM(sr.person_name), ''), NULLIF(TRIM(sr.child_name), ''), NULLIF(TRIM(sr.deceased_name), ''), NULLIF(TRIM(sr.groom_name), ''), '') AS display_name,
            sr.groom_name,
            sr.bride_name,
            YEAR(sr.event_date) AS event_year,
            sr.book_number,
            sr.page_number,
            COALESCE(c.chapel_name, 'Cathedral') AS chapel_name
        FROM sacramental_records sr
        LEFT JOIN chapels c ON c.chapel_id = sr.chapel_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderPrefix, sr.event_date DESC, sr.record_id DESC
        LIMIT 8";

$suggestions = [];
if ($stmt = $conn->prepare($sql)) {
    $allTypes  = $types . $orderTypes;
    $allParams = array_merge($params, $orderParams);
    $stmt->bind_param($allTypes, ...$allParams);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $displayName = trim((string)($row['display_name'] ?? ''));
            if ($displayName === '' && ($row['record_type'] ?? '') === 'Wedding') {
                $displayName = trim(($row['groom_name'] ?? '') . ' & ' . ($row['bride_name'] ?? ''), ' &');
            }
            if ($displayName === '') {
                continue;
            }
            $suggestions[] = [
                'record_id'    => (int)$row['record_id'],
                'reference_id' => $row['reference_id'] ?? '',
                'record_type'  => $row['record_type'] ?? '',
                'person_name'  => $displayName,
                'year'         => !empty($row['event_year']) ? (string)$row['event_year'] : '',
                'book_number'  => $row['book_number'] ?? '',
                'page_number'  => $row['page_number'] ?? '',
                'chapel_name'  => $row['chapel_name'] ?? 'Cathedral',
            ];
        }
    }
    $stmt->close();
}

echo json_encode(['suggestions' => $suggestions]);
