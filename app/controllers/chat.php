<?php
/**
 * Public AJAX Gateway for Parish Chat
 * Whitelisted in .htaccess for direct asynchronous interactions.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/ChatController.php';

$controller = new ChatController($conn, true);
$controller->handleAjax();
