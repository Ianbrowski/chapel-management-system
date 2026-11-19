<?php
/**
 * AJAX Wrapper for Priest Availability
 * Part of the Cathedral Management System (MVC Refactor)
 */
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(); }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Event.php';

$event_type     = trim($_GET['event_type'] ?? '');
$datetime       = trim($_GET['datetime'] ?? '');
$chapelId       = isset($_GET['chapel_id']) ? $_GET['chapel_id'] : null;
$excludeId      = isset($_GET['exclude_id']) && (int)$_GET['exclude_id'] > 0 ? (int)$_GET['exclude_id'] : null;
$customDuration = isset($_GET['custom_duration']) && (int)$_GET['custom_duration'] > 0 ? (int)$_GET['custom_duration'] : null;

$role = $_SESSION['role'] ?? 'Staff';
$sessionChapelId = $_SESSION['chapel_id'] ?? null;
$isAdmin = in_array($role, ['Admin', 'Cathedral Admin'], true);
$requestedChapelId = ($chapelId === null || $chapelId === '' || $chapelId === '0' || $chapelId === 'null') ? null : (int)$chapelId;

// Allow all authenticated users to check priest availability for any chapel/Cathedral scope during event planning
$eventM   = new Event($conn);
$response = $eventM->getAvailablePriests($event_type, $datetime, $excludeId, $customDuration, $requestedChapelId);

header('Content-Type: application/json');
echo json_encode($response);
