<?php
/**
 * Root-level logout entry point.
 * Directly instantiates AuthController — eliminates the redundant
 * app/controllers/logout.php middle-man wrapper.
 */
try {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/app/controllers/AuthController.php';

    $target = 'index.php';
    if (!empty($_GET['redirect'])) {
        $allowed = ['index.php', 'index.php?open_login=1', 'admin_login.php'];
        if (in_array($_GET['redirect'], $allowed, true)) {
            $target = $_GET['redirect'];
        }
    }

    $auth = new AuthController($conn);
    $auth->logout($target);
} catch (Throwable $e) {
    require_once __DIR__ . '/app/helpers/error_debug.php';
    cms_store_exception($e);
    $dest = (strpos($_SERVER['PHP_SELF'], 'views/pages/') !== false) ? 'error_500.php' : 'views/pages/error_500.php';
    header("Location: $dest");
    exit();
}
?>
