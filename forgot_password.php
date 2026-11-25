<?php
/**
 * Public Forgot Password Endpoint
 * Hands off password reset requests cleanly to AuthController.
 */
try {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/app/controllers/AuthController.php';

    $auth = new AuthController($conn);
    $auth->requestPasswordReset();
} catch (Throwable $e) {
    require_once __DIR__ . '/app/helpers/error_debug.php';
    cms_store_exception($e);
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to process password reset right now.']);
        exit();
    }
    header('Location: views/pages/error_500.php');
    exit();
}
