<?php
/**
 * Official Cathedral Confirmation Intake & Archival Entrypoint
 * Dispatches to centralized SacramentalController to preserve 1:1 paper replica formatting.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/controllers/SacramentalController.php';

$controller = new SacramentalController($conn);
$controller->handleIntakePage('Confirmation');
