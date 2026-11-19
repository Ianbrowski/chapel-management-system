<?php
/**
 * Dedicated Public Parish Request Portal
 * Allows parishioners and visitors to submit certificate requests, event inquiries, and consultations.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/controllers/PortalController.php';

$portal = new PortalController($conn);
$portal->handlePortalRequest();
