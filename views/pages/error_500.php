<?php
/**
 * 500 Server Error - Friendly UI
 * Part of the Cathedral Management System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../app/helpers/error_debug.php';

$errorPayload = $_SESSION['last_error_payload'] ?? null;
$legacyError = $_SESSION['last_error'] ?? null;
$isLocal = cms_is_local_debug();
$forceDebug = isset($_GET['debug']) && $_GET['debug'] === '1' && $isLocal;
$showDebug = $forceDebug || $isLocal;

if (!is_array($errorPayload) && is_string($legacyError) && $legacyError !== '') {
    $errorPayload = [
        'type' => 'Exception',
        'message' => $legacyError,
        'code' => 0,
        'file' => '',
        'line' => 0,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'url' => $_SERVER['REQUEST_URI'] ?? '',
        'time' => date('c'),
        'is_local' => cms_is_local_debug(),
        'trace' => '',
    ];
}

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unexpected Error - Cathedral Management System</title>
    <link rel="icon" type="image/png" href="assets/img/2323.png">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/2323.png">
    <link rel="apple-touch-icon" href="assets/img/2323.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --accent: #c9a84c;
            --error: #ef4444;
            --text: #f8fafc;
            --text-dim: #94a3b8;
        }

        html { font-size: 87.5%; }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
        }

        .container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            position: relative;
            z-index: 1;
        }

        .error-code {
            font-family: 'Cinzel', serif;
            font-size: 8rem;
            font-weight: 700;
            color: var(--surface);
            line-height: 1;
            margin-bottom: -2rem;
            user-select: none;
            position: relative;
        }

        .error-icon {
            font-size: 4rem;
            color: var(--accent);
            margin-bottom: 2rem;
            animation: pulse 2s infinite ease-in-out;
            filter: drop-shadow(0 0 15px rgba(201, 168, 76, 0.4));
        }

        h1 {
            font-family: 'Cinzel', serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }

        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--text-dim);
            margin-bottom: 2.5rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.8rem 1.8rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background-color: var(--accent);
            color: var(--bg);
        }

        .btn-primary:hover {
            background-color: #dcb95c;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(201, 168, 76, 0.2);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text);
        }

        .btn-outline:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: var(--accent);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }

        /* Subtle background elements */
        .bg-glow {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.05) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
        }

        .details-toggle {
            margin-top: 3rem;
            color: var(--text-dim);
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: underline;
            opacity: 0.5;
        }

        .details-toggle:hover { opacity: 0.8; }

        #error-details {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            text-align: left;
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--error);
            display: none;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>

    <div class="bg-glow"></div>

    <div class="container">
        <div class="error-code">500</div>
        <div class="error-icon">
            <i class="fa-solid fa-cloud-bolt"></i>
        </div>
        <h1>System Interruption</h1>
        <p>We encountered an unexpected challenge in the cathedral's digital system. Our team has been notified and is working to restore harmony.</p>
        
        <div class="actions">
            <a href="../../index.php" class="btn btn-primary">
                <i class="fa-solid fa-home"></i> Back to Home
            </a>
            <button onclick="location.reload()" class="btn btn-outline">
                <i class="fa-solid fa-rotate"></i> Try Refreshing
            </button>
        </div>

        <?php if ($showDebug && !empty($errorPayload)): ?>
            <div class="details-toggle" onclick="document.getElementById('error-details').style.display='block'">
                View technical details
            </div>
            <div id="error-details">
                <strong>Type:</strong> <?php echo htmlspecialchars((string) ($errorPayload['type'] ?? 'Exception')); ?><br>
                <strong>Message:</strong> <?php echo htmlspecialchars((string) ($errorPayload['message'] ?? 'Unknown error')); ?><br>
                <strong>Code:</strong> <?php echo htmlspecialchars((string) ($errorPayload['code'] ?? '0')); ?><br>
                <strong>File:</strong> <?php echo htmlspecialchars((string) ($errorPayload['file'] ?? '')); ?><br>
                <strong>Line:</strong> <?php echo htmlspecialchars((string) ($errorPayload['line'] ?? '')); ?><br>
                <strong>Request:</strong> <?php echo htmlspecialchars((string) (($errorPayload['method'] ?? '') . ' ' . ($errorPayload['url'] ?? ''))); ?><br>
                <strong>Time:</strong> <?php echo htmlspecialchars((string) ($errorPayload['time'] ?? '')); ?><br>
                <?php if (!empty($errorPayload['trace'])): ?>
                    <br><strong>Trace:</strong><br>
                    <?php echo nl2br(htmlspecialchars((string) $errorPayload['trace'])); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
