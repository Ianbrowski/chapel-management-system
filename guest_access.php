<?php
/**
 * Guest Access Entry Point — Cathedral Management System
 * Creates a restricted Guest session and forwards to the Member-style Dashboard.
 */
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous session and regenerate
session_regenerate_id(true);

require_once __DIR__ . '/app/controllers/AuthController.php';

// Generate fingerprint binding for security
$_SESSION['_fp'] = AuthController::makeFingerprint();

// Set up Guest credentials
$_SESSION['user_id']   = 0;
$_SESSION['username']  = 'Guest';
$_SESSION['role']      = 'Guest';
$_SESSION['full_name'] = 'Guest Visitor';
$_SESSION['chapel_id'] = null;
$_SESSION['login_at']  = time();
$_SESSION['csrf']      = bin2hex(random_bytes(32));

// Welcome flash message
$_SESSION['flash']      = 'Welcome to the IFI Cathedral Portal! You are viewing public parish schedules and services as a Guest.';
$_SESSION['flash_type'] = 'info';

// Redirect to liturgical calendar
header('Location: dashboard.php?section=events');
exit();
