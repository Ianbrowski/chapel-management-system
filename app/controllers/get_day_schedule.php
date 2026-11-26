<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Event.php';

header('Content-Type: application/json');

// ── Auth Guard ──────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

$sessionRole = $_SESSION['role'] ?? 'Staff';
$sessionChapelId = $_SESSION['chapel_id'] ?? null;
$isAdmin = in_array($sessionRole, ['Admin', 'Cathedral Admin']);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$venue = $_GET['venue'] ?? null;
$venueId = ($venue === '0' || $venue === '' || $venue === null || $venue === 'null') ? null : (int)$venue;

// ── Chapel Isolation Guard ──────────────────────────────────────────
// Relaxed: Allow all authenticated roles to view availability timelines 
// across chapels and the Cathedral to check for scheduling conflicts.
$date  = $_GET['date'] ?? null;
$exclude = isset($_GET['exclude_id']) && (int)$_GET['exclude_id'] > 0 ? (int)$_GET['exclude_id'] : null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

$isStaffOrClergy = in_array($sessionRole, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest', 'Pastor'], true);
$venueId = ($venue === '0' || $venue === '' || $venue === null || $venue === 'null') ? null : (int)$venue;
$reqVidInt = $venueId ?? 0;
$canViewTitles = $isAdmin || ($isStaffOrClergy && (($sessionChapelId === null || (int)$sessionChapelId === 0) ? $reqVidInt === 0 : (int)$sessionChapelId === $reqVidInt));

$eventModel = new Event($conn);

$sql = "SELECT e.event_id, e.title, e.start_time, e.event_type, e.status, e.custom_duration, e.chapel_id, e.location_type, c.chapel_name
        FROM events e
        LEFT JOIN chapels c ON c.chapel_id = e.chapel_id
        WHERE " . ($venueId === null ? "(e.chapel_id IS NULL OR e.chapel_id = 0)" : "e.chapel_id = ?") . "
          AND DATE(e.start_time) = ?
          AND e.status NOT IN ('Cancelled', 'Rejected')
          AND e.is_archived = 0
          AND COALESCE(e.event_type, '') != 'Ecclesiastical Certificates'
          AND COALESCE(e.event_type, '') NOT LIKE '%Certificate%'
          AND COALESCE(e.event_type, '') NOT LIKE '%Thanksgiving%'
          AND COALESCE(e.event_type, '') NOT LIKE '%Requiem%'
          AND COALESCE(e.event_type, '') NOT LIKE '%Intention%'
          AND COALESCE(e.location_type, 'Church') != 'Outside'
          AND (e.service_id IS NULL OR e.service_id NOT IN (4, 7, 8))";

$params = [];
$types  = '';
if ($venueId !== null) {
    $params[] = $venueId;
    $types   .= 'i';
}
$params[] = $date;
$types   .= 's';

if ($exclude !== null) {
    $sql .= " AND e.event_id != ?";
    $params[] = $exclude;
    $types   .= 'i';
}

$sql .= " ORDER BY e.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$events = [];
while ($row = $res->fetch_assoc()) {
    $row['duration'] = $eventModel->getEventDuration($row['event_type'], $row['custom_duration']);
    $hour = (int)date('H', strtotime($row['start_time']));
    $row['slot'] = ($hour < 12) ? 'Morning' : 'Afternoon';
    $row['venue_name'] = !empty($row['chapel_name'])
        ? $row['chapel_name']
        : (empty($row['chapel_id']) ? 'Main Cathedral' : 'Chapel #' . (int)$row['chapel_id']);
    if (!$canViewTitles) {
        $row['title'] = 'Reserved (' . $row['venue_name'] . ')';
    }
    $events[] = $row;
}

echo json_encode($events);
