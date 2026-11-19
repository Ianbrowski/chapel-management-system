<?php

if (!function_exists('cms_is_local_debug')) {
    function cms_is_local_debug(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        if ($serverName === 'localhost' || str_starts_with($serverName, 'localhost:')) {
            return true;
        }

        if ($host === 'localhost' || str_starts_with($host, 'localhost:')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('cms_store_exception')) {
    function cms_store_exception(Throwable $e): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $payload = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => (int) $e->getCode(),
            'file' => $e->getFile(),
            'line' => (int) $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'time' => date('c'),
            'is_local' => cms_is_local_debug(),
            'trace' => $e->getTraceAsString(),
        ];

        $_SESSION['last_error_payload'] = $payload;
        $_SESSION['last_error'] = $payload['message'] . " in " . $payload['file'] . ":" . $payload['line'];
    }
}

