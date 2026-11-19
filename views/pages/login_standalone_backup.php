<?php
/**
 * Dedicated System Login Page — IFI Cathedral Portal
 * Features unified Light & Dark Mode with Sacred Cathedral Aesthetic
 */
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'Guest') {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/app/controllers/BaseController.php';
$base = new BaseController($conn, false);
$csrf = $base->generateCsrf();

$errorMessage = '';
if (isset($_GET['error'])) {
    $err = $_GET['error'];
    if ($err === 'session') {
        $errorMessage = "Your session has expired. Please sign in again.";
    } else if ($err === 'wrong_pass') {
        $errorMessage = "Invalid username or password. Please verify your credentials.";
    } else if ($err === 'csrf') {
        $errorMessage = "Security validation failed. Please refresh and try again.";
    } else if ($err === 'empty_all') {
        $errorMessage = "Please enter both username and password.";
    } else if ($err === 'empty_user') {
        $errorMessage = "Username or email address is required.";
    } else if ($err === 'empty_pass') {
        $errorMessage = "Password is required.";
    } else if ($err === 'user_not_found') {
        $errorMessage = "Account not found. Please check your credentials.";
    } else if ($err === 'account_rejected') {
        $errorMessage = "Your registration could not be verified by the Cathedral Administration. Please contact the Parish Office for assistance.";
    } else if ($err === 'locked') {
        $wait = htmlspecialchars($_GET['wait'] ?? '15');
        $errorMessage = "Account temporarily locked due to multiple failed attempts. Please wait $wait minutes.";
    } else {
        $errorMessage = "Authentication failed. Please try again.";
    }
}

$successMessage = '';
if (isset($_GET['success']) && $_GET['success'] === 'logout') {
    $successMessage = "You have been safely logged out. God bless you.";
}

$noticeMessage = '';
if (isset($_GET['notice'])) {
    $notice = $_GET['notice'];
    if ($notice === 'registration_submitted') {
        $successMessage = "Your member registration has been submitted! The Cathedral Parish Office will review and approve your account shortly. Once approved, you can sign in.";
    } elseif ($notice === 'pending_approval') {
        $noticeMessage = "Your account registration has been submitted and is currently awaiting verification by the Cathedral Parish Office. Please check back soon or visit the office for expedited activation.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — IFI Cathedral Management Portal</title>
    
    <!-- Pre-render Theme Resolution Script (eliminates flash) -->
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
        /* ═══════════════════════════════════════════════════════════
           MINIMAL CATHEDRAL DESIGN SYSTEM — THEME VARIABLES
           ═══════════════════════════════════════════════════════════ */
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
            --alert-err-text: #b91c1c;

            --alert-succ-bg: rgba(16, 185, 129, 0.08);
            --alert-succ-border: rgba(16, 185, 129, 0.22);
            --alert-succ-text: #047857;

            --divider: #ececec;
            --ornament: #b8963e;
            --footer-notice-bg: #faf6ec;
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
            --alert-err-text: #fca5a5;

            --alert-succ-bg: rgba(16, 185, 129, 0.14);
            --alert-succ-border: rgba(16, 185, 129, 0.3);
            --alert-succ-text: #6ee7b7;

            --divider: #1f2937;
            --ornament: #b8963e;
            --footer-notice-bg: rgba(184, 150, 62, 0.08);
        }

        /* ═══════════════════════════════════════════════════════════
           PAGE STRUCTURE & BASE RESETS
           ═══════════════════════════════════════════════════════════ */
        html {
            font-size: 87.5%; /* 14px unified standard baseline */
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition: background-color 0.25s ease, border-color 0.25s ease, color 0.2s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-page);
            background-image: var(--bg-gradient);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 16px 20px;
            color: var(--text-body);
            position: relative;
            overflow-x: hidden;
        }

        /* Sacred background ambient arches */
        .cathedral-aura {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 500px;
            height: 280px;
            background: radial-gradient(ellipse at center, var(--ornament) 0%, transparent 70%);
            opacity: 0.08;
            pointer-events: none;
            filter: blur(50px);
            z-index: 0;
        }

        /* ═══════════════════════════════════════════════════════════
           TOP ACTION BAR (PORTAL RETURN & THEME SWITCHER)
           ═══════════════════════════════════════════════════════════ */
        .top-nav-bar {
            position: fixed;
            top: 14px;
            left: 0;
            right: 0;
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 100;
        }

        .nav-pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--top-btn-bg);
            border: 1px solid var(--top-btn-border);
            color: var(--top-btn-color);
            padding: 6px 13px;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 500;
            text-decoration: none;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            cursor: pointer;
        }

        .nav-pill-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }

        .theme-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--top-btn-bg);
            border: 1px solid var(--top-btn-border);
            color: var(--top-btn-color);
            padding: 6px 13px;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 600;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            cursor: pointer;
        }

        .theme-toggle-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }

        .theme-toggle-btn i {
            font-size: 0.88rem;
            color: var(--accent);
        }

        /* ═══════════════════════════════════════════════════════════
           MAIN LOGIN CARD & SACRED STYLING
           ═══════════════════════════════════════════════════════════ */
        .login-card {
            position: relative;
            z-index: 10;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            width: 100%;
            max-width: 360px;
            padding: 28px 24px 22px;
            box-shadow: var(--card-shadow);
            animation: cardEntrance 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Golden halo banner accent at card top */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 15%;
            right: 15%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--ornament), transparent);
            border-radius: 9999px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 18px;
        }

        .brand-logo-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 10px;
        }

        .brand-logo-wrap img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            filter: drop-shadow(0 0 16px var(--ornament));
            transition: transform 0.3s ease;
        }

        .brand-logo-wrap:hover img {
            transform: scale(1.05);
        }

        .login-brand h1 {
            font-family: 'Playfair Display', 'Cinzel', Georgia, serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: 0.04em;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .login-brand h1 span {
            color: var(--accent);
        }

        .login-brand .brand-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .sacred-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 12px 0 18px;
            color: var(--divider);
        }

        .sacred-divider::before,
        .sacred-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--divider);
        }

        .sacred-divider span {
            color: var(--ornament);
            font-size: 0.78rem;
            opacity: 0.85;
        }

        /* ═══════════════════════════════════════════════════════════
           FORM CONTROLS & INPUTS
           ═══════════════════════════════════════════════════════════ */
        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            color: var(--text-dim);
            font-size: 0.85rem;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-input {
            width: 100%;
            height: 38px;
            padding: 0 36px 0 34px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--input-color);
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            outline: none;
        }

        .form-input::placeholder {
            color: var(--input-placeholder);
            font-size: 0.8rem;
        }

        .form-input:focus {
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-ring);
        }

        .input-wrap:focus-within .input-icon {
            color: var(--accent);
        }

        .pw-toggle-btn {
            position: absolute;
            right: 10px;
            background: transparent;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            padding: 4px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pw-toggle-btn:hover {
            color: var(--accent);
        }

        /* ═══════════════════════════════════════════════════════════
           SUBMIT BUTTON
           ═══════════════════════════════════════════════════════════ */
        .btn-submit {
            width: 100%;
            height: 38px;
            background: var(--accent-gradient);
            color: var(--accent-text);
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 0.86rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--accent-hover-shadow);
            filter: brightness(1.04);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* ═══════════════════════════════════════════════════════════
           ALERT BANNERS
           ═══════════════════════════════════════════════════════════ */
        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.84rem;
            line-height: 1.45;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            backdrop-filter: blur(8px);
        }

        .alert-error {
            background: var(--alert-err-bg);
            border: 1px solid var(--alert-err-border);
            color: var(--alert-err-text);
        }

        .alert-success {
            background: var(--alert-succ-bg);
            border: 1px solid var(--alert-succ-border);
            color: var(--alert-succ-text);
        }

        .alert-warning {
            background: rgba(197, 160, 89, 0.14);
            border: 1px solid rgba(197, 160, 89, 0.35);
            color: #8C6A18;
        }

        [data-theme="dark"] .alert-warning {
            background: rgba(197, 160, 89, 0.16);
            border-color: rgba(197, 160, 89, 0.4);
            color: #D8B56F;
        }

        .alert-box i {
            font-size: 1.05rem;
            margin-top: 1px;
            flex-shrink: 0;
        }

        /* ═══════════════════════════════════════════════════════════
           SACRED FOOTER & NOTICES
           ═══════════════════════════════════════════════════════════ */
        .login-footer-notice {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--divider);
            text-align: center;
        }

        .login-footer-notice p {
            font-size: 0.78rem;
            color: var(--text-dim);
            line-height: 1.5;
        }

        .login-footer-notice strong {
            color: var(--text-muted);
            display: block;
            margin-bottom: 4px;
        }

        .page-copyright {
            margin-top: 24px;
            text-align: center;
            font-size: 0.74rem;
            color: var(--text-dim);
            z-index: 10;
        }

        @media (max-width: 480px) {
            body {
                padding: 70px 16px 20px;
            }
            .login-card {
                padding: 30px 20px;
                border-radius: 16px;
            }
            .top-nav-bar {
                padding: 0 16px;
                top: 14px;
            }
            .theme-toggle-text {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="cathedral-aura"></div>

    <!-- Top Action Bar with Portal Link & Theme Switcher -->
    <header class="top-nav-bar">
        <a href="index.php" class="nav-pill-btn" title="Return to Cathedral Main Portal">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Cathedral Portal</span>
        </a>

        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle light or dark theme" title="Toggle Light / Dark Mode">
            <i class="fa-regular fa-sun" id="themeToggleIcon"></i>
            <span class="theme-toggle-text" id="themeToggleLabel">Theme</span>
        </button>
    </header>

    <!-- Main Sacred Login Card -->
    <main class="login-card">
        <div class="login-brand">
            <div class="brand-logo-wrap">
                <img src="assets/img/2323.png" alt="IFI Cathedral Crest">
            </div>
            <h1>IFI <span>CATHEDRAL</span></h1>
            <p class="brand-sub">Church Management & Pastoral Information Portal</p>
        </div>

        <div class="sacred-divider">
            <span>☩</span>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert-box alert-error" role="alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Sign-in Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($noticeMessage)): ?>
            <div class="alert-box alert-warning" role="alert">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>Verification Notice:</strong> <?php echo htmlspecialchars($noticeMessage); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="alert-box alert-success" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Success:</strong> <?php echo htmlspecialchars($successMessage); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="app/controllers/authenticate.php" id="loginForm" autocomplete="on">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

            <div class="form-group">
                <label for="username" class="form-label">Username or Email</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input 
                        type="text" 
                        id="username" 
                        name="user" 
                        class="form-input" 
                        placeholder="Enter username or email" 
                        maxlength="100"
                        required 
                        autofocus 
                        autocomplete="username"
                        spellcheck="false"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Enter your account password" 
                        maxlength="72"
                        required 
                        autocomplete="current-password"
                    >
                    <button type="button" class="pw-toggle-btn" id="pwToggleBtn" aria-label="Show or hide password" title="Toggle password visibility">
                        <i class="fa-regular fa-eye" id="pwToggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <span>Sign In to Portal</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div style="margin-top: 14px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <a href="register.php" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; font-weight: 600; color: var(--accent); text-decoration: none; padding: 7px 12px; border-radius: 8px; border: 1px dashed var(--card-border); background: var(--footer-notice-bg); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--card-border)'">
                <i class="fa-solid fa-user-plus"></i> Register as Member
            </a>
            <a href="guest_access.php" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; font-weight: 600; color: var(--text-heading); text-decoration: none; padding: 7px 12px; border-radius: 8px; border: 1px solid var(--card-border); background: var(--top-btn-bg); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--card-border)'">
                <i class="fa-solid fa-compass"></i> Explore as Guest
            </a>
        </div>

        <div class="login-footer-notice">
            <strong>Cathedral Access Notice</strong>
            <p>This portal is reserved for authorized clergy, officers, and registered cathedral members. For account credentials or technical assistance, please visit the Cathedral Parish Office.</p>
        </div>
    </main>

    <footer class="page-copyright">
        <p>&copy; <?php echo date('Y'); ?> Iglesia Filipina Independiente &bull; Cathedral Management System</p>
    </footer>

    <!-- Theme Switcher & Interactive Scripts -->
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

        // Password visibility toggle
        (function initPasswordToggle() {
            const pwInput = document.getElementById('password');
            const toggleBtn = document.getElementById('pwToggleBtn');
            const toggleIcon = document.getElementById('pwToggleIcon');

            if (toggleBtn && pwInput) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = pwInput.type === 'password';
                    pwInput.type = isPassword ? 'text' : 'password';
                    if (toggleIcon) {
                        toggleIcon.className = isPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
                    }
                });
            }
        })();

        // Submit button loading animation
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        if (loginForm && submitBtn) {
            loginForm.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.85';
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Authenticating...</span>';
            });
        }
    </script>
</body>
</html>
