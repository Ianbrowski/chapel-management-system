<?php
/**
 * Real-time Notification Dispatcher
 * Returns JSON with latest maintenance and operational stats.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/Inquiry.php';

require_once __DIR__ . '/BaseController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'error' => 'Unauthenticated',
        'events' => 0,
        'inquiries' => 0,
        'stale' => 0,
        'timestamp' => date('H:i:s')
    ]);
    exit();
}

try {
    $base = new BaseController($conn, true);
    $perms = $base->getPermissions();
    $role = $base->getRole();
    $chapelId = $base->getChapelId();

    // Clean up Admin/Cathedral Admin to see everything
    $chapelFilter = in_array($role, ['Admin', 'Cathedral Admin']) ? null : $chapelId;

    $canApproveEvents = $perms['can_approve_events'] ?? false;
    $canManageInquiries = $perms['can_manage_inquiries'] ?? false;
    $eventM = new Event($conn);
    $inquiryM = new Inquiry($conn);

    $pending_events = $canApproveEvents ? $eventM->getCountByStatus('Pending', $chapelFilter) : 0;
    $pending_inquiries = $canManageInquiries ? $inquiryM->getActiveCount($chapelFilter) : 0; 
    $stale_events = 0;

    // Only check stale if user has permission (mapped to role in front-end)
    if ($canApproveEvents) {
        $auditScope = ($role === 'Chapel Admin') ? 'chapel' : null;
        $stale_events = count($eventM->getStaleConfirmedEvents($auditScope, $chapelFilter));
    }

    header('Content-Type: application/json');
    echo json_encode([
        'events' => (int)$pending_events,
        'inquiries' => (int)$pending_inquiries,
        'stale' => (int)$stale_events,
        'timestamp' => date('H:i:s')
    ]);
} catch (Throwable $e) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'error' => 'Service temporarily unavailable',
        'events' => 0,
        'inquiries' => 0,
        'stale' => 0,
        'timestamp' => date('H:i:s')
    ]);
}
