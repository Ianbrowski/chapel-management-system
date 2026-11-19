<?php
/**
 * Authentication Wrapper Router
 * Hands off the request to the MVC AuthController.
 */
try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/AuthController.php';

    $auth = new AuthController($conn);
    $auth->login();
} catch (Throwable $e) {
    require_once __DIR__ . '/../helpers/error_debug.php';
    cms_store_exception($e);
    header("Location: ../../views/pages/error_500.php");
    exit();
}
