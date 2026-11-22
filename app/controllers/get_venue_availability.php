<?php
/**
 * AJAX Wrapper for Venue Availability
 * Part of the Cathedral Management System (MVC Refactor)
 */
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(); }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Event.php';

$venue          = trim($_GET['venue'] ?? '');
$datetime       = trim($_GET['datetime'] ?? '');
$eventType      = trim($_GET['event_type'] ?? 'Other');
$excludeId      = isset($_GET['exclude_id']) && (int)$_GET['exclude_id'] > 0 ? (int)$_GET['exclude_id'] : null;
$customDuration = isset($_GET['custom_duration']) && (int)$_GET['custom_duration'] > 0 ? (int)$_GET['custom_duration'] : null;
$locationType   = in_array($_GET['location_type'] ?? '', ['Church', 'Outside'], true) ? $_GET['location_type'] : 'Church';

$role = $_SESSION['role'] ?? 'Staff';
$sessionChapelId = $_SESSION['chapel_id'] ?? null;
$isAdmin = in_array($role, ['Admin', 'Cathedral Admin'], true);
$requestedVenueId = ($venue === '' || $venue === '0' || $venue === 'null') ? null : (int)$venue;

$eventM = new Event($conn);
if ($venue === '') {
    $response = $eventM->getVenueAvailability($datetime, $eventType, $excludeId, $customDuration, $locationType);
} else {
    $response = $eventM->checkVenueConflict($requestedVenueId, $datetime, $eventType, $excludeId, $customDuration, $locationType);
}

header('Content-Type: application/json');
echo json_encode($response);
