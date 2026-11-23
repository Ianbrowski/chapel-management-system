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

if (!$date) {
    echo json_encode([]);
    exit;
}

$isStaffOrClergy = in_array($sessionRole, ['Admin', 'Cathedral Admin', 'Chapel Admin', 'Staff', 'Priest', 'Pastor'], true);
$venueId = ($venue === '0' || $venue === '' || $venue === null || $venue === 'null') ? null : (int)$venue;
$reqVidInt = $venueId ?? 0;
$canViewTitles = $isAdmin || ($isStaffOrClergy && (($sessionChapelId === null || (int)$sessionChapelId === 0) ? $reqVidInt === 0 : (int)$sessionChapelId === $reqVidInt));

$eventModel = new Event($conn);

$sql = "SELECT event_id, title, start_time, event_type, status, custom_duration 
        FROM events 
        WHERE " . ($venueId === null ? "(chapel_id IS NULL OR chapel_id = 0)" : "chapel_id = ?") . "
          AND DATE(start_time) = ? 
          AND status NOT IN ('Cancelled', 'Rejected') 
          AND is_archived = 0
          AND COALESCE(event_type, '') != 'Ecclesiastical Certificates'
          AND COALESCE(event_type, '') NOT LIKE '%Certificate%'
          AND COALESCE(event_type, '') NOT LIKE '%Thanksgiving%'
          AND COALESCE(event_type, '') NOT LIKE '%Requiem%'
          AND COALESCE(event_type, '') NOT LIKE '%Intention%'
          AND COALESCE(location_type, 'Church') != 'Outside'
          AND (service_id IS NULL OR service_id NOT IN (4, 7, 8))";

$params = [];
$types  = '';
if ($venueId !== null) {
    $params[] = $venueId;
    $types   .= 'i';
}
$params[] = $date;
$types   .= 's';

if ($exclude !== null) {
    $sql .= " AND event_id != ?";
    $params[] = $exclude;
    $types   .= 'i';
}

$sql .= " ORDER BY start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$events = [];
while ($row = $res->fetch_assoc()) {
    $row['duration'] = $eventModel->getEventDuration($row['event_type'], $row['custom_duration']);
    $hour = (int)date('H', strtotime($row['start_time']));
    $row['slot'] = ($hour < 12) ? 'Morning' : 'Afternoon';
    if (!$canViewTitles) {
        $row['title'] = 'Reserved Sanctuary Slot';
    }
    $events[] = $row;
}

echo json_encode($events);
