<?php
/**
 * Public Member Registration Page — IFI Cathedral Management System
 * Allows parishioners to register for a Cathedral Member account.
 * Accounts are held in a 'pending' state awaiting Parish Office verification and approval.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/controllers/BaseController.php';
require_once __DIR__ . '/app/models/User.php';
require_once __DIR__ . '/app/models/Chapel.php';
require_once __DIR__ . '/app/models/AuditLogger.php';

// Redirect if already logged in as a registered member/user
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'Guest') {
    header("Location: dashboard.php");
    exit();
}

$base = new BaseController($conn, false);
$userModel = new User($conn);
$chapelModel = new Chapel($conn);

$chapels = $chapelModel->getAll();

require_once __DIR__ . '/app/helpers/RateLimiter.php';

$errorMessage = '';
$submittedData = [
    'full_name' => '',
    'user'      => '',
    'email'     => '',
    'phone'     => '',
    'chapel_id' => '0'
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $ip = RateLimiter::getClientIp();
    $rateKey = 'register:' . $ip;

    if (RateLimiter::tooManyAttempts($rateKey, 5, 300)) {
        $waitMins = max(1, (int)ceil(RateLimiter::availableIn($rateKey, 300) / 60));
        $errorMessage = "Too many registration attempts. Please wait $waitMins minute(s) before trying again.";
    } else {
        RateLimiter::hit($rateKey, 300);
        $csrf = $_POST['csrf'] ?? '';
        if (!$base->validateCsrf($csrf)) {
            $errorMessage = "Security validation failed. Please refresh the page and try again.";
        } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = strtolower(trim($_POST['user'] ?? ''));
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone'] ?? '');
        $chapelId = isset($_POST['chapel_id']) && $_POST['chapel_id'] !== '' ? (int)$_POST['chapel_id'] : 0;
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $submittedData = [
            'full_name' => $fullName,
            'user'      => $username,
            'email'     => $email,
            'phone'     => $phone,
            'chapel_id' => (string)$chapelId
        ];

        // Validations
        if (empty($fullName) || empty($username) || empty($password)) {
            $errorMessage = "Full name, username, and password are required.";
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            $errorMessage = "Full name must be between 2 and 100 characters.";
        } elseif (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-z0-9_.-]+$/', $username)) {
            $errorMessage = "Username must be between 3 and 30 characters and contain only letters, numbers, dots, or underscores.";
        } elseif (!empty($email) && strlen($email) > 100) {
            $errorMessage = "Email address cannot exceed 100 characters.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Please enter a valid email address.";
        } elseif (!empty($phone) && (strlen($phone) > 20 || !preg_match('/^[0-9+ \-()]{7,20}$/', $phone))) {
            $errorMessage = "Contact number must be between 7 and 20 digits/valid characters.";
        } elseif (strlen($password) < 6) {
            $errorMessage = "Password must be at least 6 characters long.";
        } elseif (strlen($password) > 72) {
            $errorMessage = "Password cannot exceed 72 characters.";
        } elseif ($password !== $confirm) {
            $errorMessage = "Passwords do not match. Please re-enter your password.";
        } elseif ($userModel->exists($username)) {
            $errorMessage = "The username '@" . htmlspecialchars($username) . "' is already taken. Please choose another.";
        } else {
            // Check if email already registered
            if (!empty($email)) {
                $chkEmail = $conn->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
                if ($chkEmail) {
                    $chkEmail->bind_param('s', $email);
                    $chkEmail->execute();
                    if ($chkEmail->get_result()->num_rows > 0) {
                        $errorMessage = "An account with this email address already exists.";
                    }
                    $chkEmail->close();
                }
            }

            if (empty($errorMessage)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $created = $userModel->create(
                    $username,
                    $hashedPassword,
                    'Member',
                    $email ?: null,
                    $chapelId,
                    $fullName,
                    'pending',
                    $phone ?: null
                );

                if ($created) {
                    // Log institutional audit
                    $newUserId = $conn->insert_id;
                    $logDetails = [
                        'user_id'   => $newUserId,
                        'username'  => $username,
                        'full_name' => $fullName,
                        'email'     => $email,
                        'phone'     => $phone,
                        'role'      => 'Member',
                        'status'    => 'pending'
                    ];
                    
                    AuditLogger::logAnonymous(
                        $conn,
                        'registered',
                        'Users',
                        "New member self-registration submitted: @{$username} ({$fullName})",
                        null,
                        $logDetails
                    );

                    RateLimiter::clear($rateKey);
                    header("Location: index.php?notice=registration_submitted");
                    exit();
                } else {
                    $errorMessage = "An unexpected error occurred while creating your account. Please try again or visit the Cathedral Parish Office.";
                }
            }
        }
    }
}
}

$csrfToken = $base->generateCsrf();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Registration — IFI Cathedral</title>
    
    <!-- Theme Resolution Script -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || localStorage.getItem('portalTheme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Cinzel:wght@500;600;700;800&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/img/2323.png">

    <style>
        :root {
            /* Light Theme (Clean Minimal White & Gold) */
            --bg-page: #f8fafc;
            --bg-gradient: radial-gradient(circle at 50% 0%, rgba(184, 150, 62, 0.07) 0%, transparent 60%),
                           linear-gradient(180deg, #ffffff 0%, #f4f5f7 100%);
            --card-bg: #ffffff;
            --card-border: #ececec;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03);
            
            --text-heading: #1a1a1a;
            --text-body: #333333;
            --text-muted: #8a8a8a;
            --text-dim: #9e9e9e;

            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            --input-color: #1a1a1a;
            --input-placeholder: #94a3b8;
            --input-focus-border: #b8963e;
            --input-focus-ring: rgba(184, 150, 62, 0.16);

            --accent: #b8963e;
            --accent-hover: #a38232;
            --accent-soft: #faf6ec;
            --accent-gradient: linear-gradient(135deg, #c5a043 0%, #a38232 100%);
            --accent-text: #ffffff;
            --accent-hover-shadow: 0 6px 20px rgba(184, 150, 62, 0.28);
            
            --top-btn-bg: #ffffff;
            --top-btn-border: #ececec;
            --top-btn-color: #1a1a1a;

            --alert-err-bg: rgba(239, 68, 68, 0.08);
            --alert-err-border: rgba(239, 68, 68, 0.22);
            --alert-err-color: #b91c1c;
            
            --footer-notice-bg: #faf6ec;
            --footer-notice-border: rgba(184, 150, 62, 0.2);
        }

        [data-theme="dark"] {
            /* Dark Theme (Obsidian Cathedral & Soft Gold) */
            --bg-page: #0b0f19;
            --bg-gradient: radial-gradient(circle at 50% 0%, rgba(184, 150, 62, 0.1) 0%, transparent 65%),
                           linear-gradient(180deg, #111827 0%, #0b0f19 100%);
            --card-bg: #111827;
            --card-border: #1f2937;
            --card-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);

            --text-heading: #f9fafb;
            --text-body: #e5e7eb;
            --text-muted: #9ca3af;
            --text-dim: #6b7280;

            --input-bg: #1a2234;
            --input-border: #374151;
            --input-color: #f9fafb;
            --input-placeholder: #6b7280;
            --input-focus-border: #b8963e;
            --input-focus-ring: rgba(184, 150, 62, 0.25);

            --accent: #b8963e;
            --accent-hover: #c5a043;
            --accent-soft: rgba(184, 150, 62, 0.14);
            --accent-gradient: linear-gradient(135deg, #c5a043 0%, #a38232 100%);
            --accent-text: #ffffff;
            --accent-hover-shadow: 0 6px 20px rgba(184, 150, 62, 0.35);

            --top-btn-bg: #1e293b;
            --top-btn-border: #334155;
            --top-btn-color: #f1f5f9;

            --alert-err-bg: rgba(239, 68, 68, 0.14);
            --alert-err-border: rgba(239, 68, 68, 0.3);
            --alert-err-color: #fca5a5;

            --footer-notice-bg: rgba(184, 150, 62, 0.08);
            --footer-notice-border: rgba(184, 150, 62, 0.2);
        }

        html {
            font-size: 87.5%; /* 14px unified standard baseline */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-page);
            background-image: var(--bg-gradient);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
            color: var(--text-body);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .top-nav {
            position: fixed;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            pointer-events: none;
        }

        .top-btn {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--top-btn-bg);
            border: 1px solid var(--top-btn-border);
            border-radius: 9999px;
            color: var(--top-btn-color);
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            backdrop-filter: blur(8px);
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .top-btn:hover {
            color: var(--accent);
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .register-card {
            width: 100%;
            max-width: 580px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1.25rem;
            padding: 2.25rem 2.25rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(16px);
            transition: all 0.3s ease;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .register-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .cathedral-crest {
            width: 68px;
            height: 68px;
            margin: 0 auto 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 4px 12px rgba(201, 168, 76, 0.25));
        }

        .cathedral-crest img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .cathedral-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
            background: var(--footer-notice-bg);
            border: 1px solid var(--footer-notice-border);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            margin-bottom: 0.5rem;
        }

        .register-title {
            font-family: 'Playfair Display', 'Cinzel', Georgia, serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: 0.02em;
            margin-bottom: 0.35rem;
        }

        .register-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        .alert-box {
            padding: 0.75rem 1rem;
            border-radius: 0.65rem;
            font-size: 0.83rem;
            line-height: 1.45;
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            margin-bottom: 1.25rem;
            background: var(--alert-err-bg);
            border: 1px solid var(--alert-err-border);
            color: var(--alert-err-color);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 540px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
                gap: 0.85rem;
            }
            .register-card {
                padding: 1.75rem 1.25rem;
            }
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-heading);
            margin-bottom: 0.35rem;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 0.85rem;
            color: var(--accent);
            font-size: 0.85rem;
            pointer-events: none;
            opacity: 0.85;
        }

        .form-input, .form-select {
            width: 100%;
            height: 42px;
            padding: 0.5rem 0.85rem 0.5rem 2.35rem;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 0.55rem;
            color: var(--input-color);
            font-family: inherit;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23c9a84c' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 12px) center;
            cursor: pointer;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-ring);
        }

        .pw-toggle-btn {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            padding: 0.25rem;
            font-size: 0.85rem;
        }

        .pw-toggle-btn:hover {
            color: var(--accent);
        }

        .btn-submit {
            width: 100%;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--accent-gradient);
            border: none;
            border-radius: 0.55rem;
            color: var(--accent-text);
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: var(--accent-hover-shadow);
        }

        .register-footer-notice {
            margin-top: 1.25rem;
            padding: 0.85rem 1rem;
            background: var(--footer-notice-bg);
            border: 1px solid var(--footer-notice-border);
            border-radius: 0.65rem;
            font-size: 0.76rem;
            color: var(--text-muted);
            line-height: 1.45;
            text-align: center;
        }

        .login-cta-box {
            text-align: center;
            margin-top: 1.25rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .login-cta-box a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
        }

        .login-cta-box a:hover {
            text-decoration: underline;
        }

        .page-copyright {
            margin-top: 0.5rem;
            font-size: 0.72rem;
            color: var(--text-dim);
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <a href="index.php" class="top-btn" title="Return to Cathedral Homepage">
            <i class="fa-solid fa-church"></i>
            <span>Cathedral Home</span>
        </a>
        <button id="themeToggleBtn" class="top-btn" title="Toggle Theme" aria-label="Toggle Theme">
            <i id="themeToggleIcon" class="fa-regular fa-sun"></i>
            <span id="themeToggleLabel">Light Mode</span>
        </button>
    </nav>

    <!-- Main Registration Card -->
    <main class="register-card">
        <header class="register-header">
            <div class="cathedral-crest">
                <img src="assets/img/2323.png" alt="Cathedral Seal">
            </div>
            <div class="cathedral-badge">
                <i class="fa-solid fa-user-plus"></i> New Member Registration
            </div>
            <h1 class="register-title">Register as Member</h1>
            <p class="register-subtitle">
                Create an account to request services, schedule Mass intentions, and view your church records.
            </p>
        </header>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert-box">
                <i class="fa-solid fa-circle-exclamation" style="margin-top: 2px;"></i>
                <div><?php echo htmlspecialchars($errorMessage); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="full_name" class="form-label">Full Legal Name *</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-id-card input-icon"></i>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-input" 
                            placeholder="e.g. Maria Santos" 
                            value="<?php echo htmlspecialchars($submittedData['full_name']); ?>"
                            minlength="2"
                            maxlength="100"
                            required 
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Desired Username *</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="user" 
                            class="form-input" 
                            placeholder="e.g. msantos" 
                            value="<?php echo htmlspecialchars($submittedData['user']); ?>"
                            minlength="3"
                            maxlength="30"
                            pattern="^[a-zA-Z0-9_.-]+$"
                            title="3 to 30 alphanumeric characters, dots, underscores, or dashes"
                            required 
                            autocomplete="username"
                            spellcheck="false"
                        >
                    </div>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address *</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="e.g. maria@email.com" 
                            value="<?php echo htmlspecialchars($submittedData['email']); ?>"
                            maxlength="100"
                            required 
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Contact / Mobile #</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-phone input-icon"></i>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-input" 
                            placeholder="e.g. 09171234567" 
                            value="<?php echo htmlspecialchars($submittedData['phone']); ?>"
                            maxlength="20"
                            pattern="[0-9+ \-()]{7,20}"
                            title="7 to 20 digits and valid phone characters (+ - ())"
                        >
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="chapel_id" class="form-label">Local Chapel or Parish</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-place-of-worship input-icon"></i>
                    <select id="chapel_id" name="chapel_id" class="form-select">
                        <option value="0" <?php echo ($submittedData['chapel_id'] === '0') ? 'selected' : ''; ?>>
                            IFI Cathedral (Main Church)
                        </option>
                        <?php foreach ($chapels as $ch): ?>
                            <option value="<?php echo $ch['chapel_id']; ?>" <?php echo ($submittedData['chapel_id'] == $ch['chapel_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ch['chapel_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="password" class="form-label">
                        Password *
                        <span style="font-size: 0.72rem; color: var(--gold-pale); opacity: 0.8; font-weight: 400; float: right;">6-72 chars</span>
                    </label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Min. 6 characters" 
                            minlength="6"
                            maxlength="72"
                            required 
                            autocomplete="new-password"
                        >
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('password', this)" aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        Confirm Password *
                        <span style="font-size: 0.72rem; color: var(--gold-pale); opacity: 0.8; font-weight: 400; float: right;">6-72 chars</span>
                    </label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="form-input" 
                            placeholder="Re-enter password" 
                            minlength="6"
                            maxlength="72"
                            required 
                            autocomplete="new-password"
                        >
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('confirm_password', this)" aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fa-solid fa-user-plus"></i>
                <span>Create Account</span>
            </button>
        </form>

        <div class="register-footer-notice">
            <i class="fa-solid fa-shield-halved" style="color: var(--accent); margin-right: 4px;"></i>
            <strong>Account Verification Notice:</strong>
            New accounts are reviewed and confirmed by the parish office before access is activated.
        </div>

        <div class="login-cta-box">
            Already have a verified account? <a href="index.php?open_login=1">Sign In to Portal</a>
        </div>
    </main>

    <footer class="page-copyright">
        <p>&copy; <?php echo date('Y'); ?> Iglesia Filipina Independiente &bull; Cathedral Management System</p>
    </footer>

    <!-- Theme Switcher & Interactive Script -->
    <script>
        (function initTheme() {
            const toggleBtn = document.getElementById('themeToggleBtn');
            const toggleIcon = document.getElementById('themeToggleIcon');
            const toggleLabel = document.getElementById('themeToggleLabel');

            function syncThemeUI(theme) {
                const isDark = (theme === 'dark');
                if (toggleIcon) {
                    toggleIcon.className = isDark ? 'fa-regular fa-sun' : 'fa-regular fa-moon';
                }
                if (toggleLabel) {
                    toggleLabel.textContent = isDark ? 'Light Mode' : 'Dark Mode';
                }
            }

            const activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            syncThemeUI(activeTheme);

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    const current = document.documentElement.getAttribute('data-theme') || 'dark';
                    const next = (current === 'dark') ? 'light' : 'dark';
                    
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('theme', next);
                    localStorage.setItem('portalTheme', next);
                    syncThemeUI(next);
                });
            }
        })();

        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (!input || !icon) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-regular fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa-regular fa-eye';
            }
        }

        // Live input limits and scrubbing
        document.addEventListener('DOMContentLoaded', function () {
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.addEventListener('input', function () {
                    this.value = this.value.toLowerCase().replace(/[^a-z0-9_.-]/g, '').slice(0, 30);
                });
            }

            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9+\-()\s]/g, '').slice(0, 20);
                });
            }

            const fullNameInput = document.getElementById('full_name');
            if (fullNameInput) {
                fullNameInput.addEventListener('input', function () {
                    if (this.value.length > 100) {
                        this.value = this.value.slice(0, 100);
                    }
                });
            }
        });
    </script>
</body>
</html>
