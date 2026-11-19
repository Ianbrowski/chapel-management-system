<?php
/**
 * Dedicated Super Administrator Login Gateway (Direct-Link Only)
 * Strictly restricted to Super Admin (role = 'Admin') accounts.
 * Super Admin login via the default public portal (index.php) is blocked for security.
 */
require_once __DIR__ . '/config/config.php';

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
}

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'Guest') {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/app/controllers/BaseController.php';
$base = new BaseController($conn, false);
$csrf = $base->generateCsrf();

if (empty($_SESSION['admin_login_token'])) {
    $_SESSION['admin_login_token'] = bin2hex(random_bytes(32));
}
$adminPortalToken = $_SESSION['admin_login_token'];

$errorMessage = '';
if (isset($_GET['error'])) {
    $err = $_GET['error'];
    if ($err === 'session') {
        $errorMessage = "Your Super Admin session has expired. Please sign in again.";
    } elseif ($err === 'wrong_pass') {
        $errorMessage = "Invalid Super Administrator credentials.";
    } elseif ($err === 'csrf') {
        $errorMessage = "Security validation failed. Please refresh and try again.";
    } elseif ($err === 'empty_all') {
        $errorMessage = "Please enter both username and password.";
    } elseif ($err === 'empty_user') {
        $errorMessage = "Super Admin username or email is required.";
    } elseif ($err === 'empty_pass') {
        $errorMessage = "Password is required.";
    } elseif ($err === 'locked' || $err === 'ip_locked') {
        $wait = htmlspecialchars($_GET['wait'] ?? '15');
        $errorMessage = "Gateway temporarily locked due to multiple failed attempts. Please wait $wait minutes.";
    } else {
        $errorMessage = "Authentication failed. Please verify your Super Admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Super Administrator Gateway — IFI Cathedral Management System</title>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || localStorage.getItem('portalTheme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="assets/img/2323.png">
    <style>
        :root {
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-heading: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --input-bg: #f8fafc;
            --input-border: #cbd5e1;
            --gold: #b8963e;
            --gold-hover: #9e7f2f;
            --gold-subtle: rgba(184, 150, 62, 0.12);
            --btn-bg: #0f172a;
            --btn-text: #ffffff;
        }
        [data-theme="dark"] {
            --bg-page: #090b10;
            --card-bg: #11151f;
            --card-border: rgba(201, 168, 76, 0.22);
            --text-heading: #f8fafc;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --input-bg: #0b0e16;
            --input-border: #1e293b;
            --gold: #c9a84c;
            --gold-hover: #dfc068;
            --gold-subtle: rgba(201, 168, 76, 0.14);
            --btn-bg: linear-gradient(135deg, #c9a84c 0%, #a6842d 100%);
            --btn-text: #090b10;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--bg-page);
            background-image: radial-gradient(circle at 50% 0%, var(--gold-subtle) 0%, transparent 65%);
            color: var(--text-body);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .admin-gateway-card {
            width: 100%;
            max-width: 430px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 36px 32px 30px;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.35);
            position: relative;
            overflow: hidden;
        }
        .admin-gateway-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        .gateway-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .crest-wrap {
            width: 66px;
            height: 66px;
            margin: 0 auto 12px;
            border-radius: 50%;
            padding: 6px;
            background: var(--gold-subtle);
            border: 1px solid rgba(201, 168, 76, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .crest-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .security-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gold);
            background: var(--gold-subtle);
            border: 1px solid rgba(201, 168, 76, 0.3);
            padding: 4px 11px;
            border-radius: 999px;
            margin-bottom: 10px;
        }
        .gateway-title {
            font-family: 'Cinzel', serif;
            font-size: 1.32rem;
            font-weight: 700;
            color: var(--text-heading);
            letter-spacing: 0.02em;
            margin-bottom: 4px;
        }
        .gateway-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.45;
        }
        .alert-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 13px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #f87171;
            font-size: 0.82rem;
            line-height: 1.4;
            margin-bottom: 18px;
        }
        [data-theme="light"] .alert-box {
            color: #b91c1c;
            background: #fef2f2;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--gold);
            font-size: 0.9rem;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            padding: 12px 42px 12px 40px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            color: var(--text-heading);
            font-size: 0.92rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }
        .form-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-subtle);
        }
        .pw-toggle {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            font-size: 0.92rem;
        }
        .pw-toggle:hover {
            color: var(--gold);
        }
        .btn-submit {
            width: 100%;
            margin-top: 6px;
            padding: 13px 18px;
            border-radius: 10px;
            border: none;
            background: var(--btn-bg);
            color: var(--btn-text);
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }
        .btn-submit:hover {
            opacity: 0.94;
            transform: translateY(-1px);
        }
        .btn-submit:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }
        .gateway-footer {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid var(--card-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.76rem;
            color: var(--text-muted);
        }
        .gateway-footer a {
            color: var(--text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }
        .gateway-footer a:hover {
            color: var(--gold);
        }
        .theme-btn {
            background: transparent;
            border: 1px solid var(--card-border);
            color: var(--text-muted);
            border-radius: 6px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .theme-btn:hover {
            color: var(--gold);
            border-color: var(--gold);
        }
    </style>
</head>
<body>

<div class="admin-gateway-card">
    <div class="gateway-header">
        <div class="crest-wrap">
            <img src="assets/img/2323.png" alt="IFI Cathedral Seal">
        </div>
        <div class="security-pill">
            <i class="fa-solid fa-shield-halved"></i> Restricted Direct-Link Gateway
        </div>
        <h1 class="gateway-title">Super Admin Sign In</h1>
        <p class="gateway-sub">Iglesia Filipina Independiente &bull; Cathedral of St. Michael the Archangel</p>
    </div>

    <div id="adminLoginAlert" class="alert-box" style="<?php echo $errorMessage !== '' ? 'display:flex;' : 'display:none;'; ?>" role="alert">
        <i class="fa-solid fa-triangle-exclamation" style="margin-top:2px;"></i>
        <div id="adminAlertText"><?php echo htmlspecialchars($errorMessage); ?></div>
    </div>

    <form id="superAdminLoginForm" method="POST" action="app/controllers/authenticate.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="login_channel" value="super_admin">
        <input type="hidden" name="admin_portal_token" value="<?php echo htmlspecialchars($adminPortalToken); ?>">

        <div class="form-group">
            <label for="adminUsername" class="form-label">Super Admin Username</label>
            <div class="input-wrap">
                <i class="fa-solid fa-user-shield input-icon"></i>
                <input
                    type="text"
                    id="adminUsername"
                    name="user"
                    class="form-input"
                    placeholder="Enter Super Admin username"
                    maxlength="50"
                    required
                    autofocus
                    spellcheck="false"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="adminPassword" class="form-label">Master Password</label>
            <div class="input-wrap">
                <i class="fa-solid fa-key input-icon"></i>
                <input
                    type="password"
                    id="adminPassword"
                    name="password"
                    class="form-input"
                    placeholder="Enter Super Admin password"
                    maxlength="72"
                    required
                >
                <button type="button" class="pw-toggle" id="adminPwToggle" aria-label="Toggle password visibility" title="Show/Hide password">
                    <i class="fa-regular fa-eye" id="adminPwIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit" id="adminSubmitBtn">
            <span id="adminSubmitText">Authorize &amp; Enter Console</span>
            <i class="fa-solid fa-arrow-right-to-bracket" id="adminSubmitIcon"></i>
        </button>
    </form>

    <div class="gateway-footer">
        <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Public Home</a>
        <button type="button" class="theme-btn" id="adminThemeBtn" title="Switch Theme">
            <i class="fa-regular fa-sun" id="adminThemeIcon"></i> Theme
        </button>
    </div>
</div>

<script>
    (function () {
        const pwToggle = document.getElementById('adminPwToggle');
        const pwInput = document.getElementById('adminPassword');
        const pwIcon = document.getElementById('adminPwIcon');
        if (pwToggle && pwInput && pwIcon) {
            pwToggle.addEventListener('click', () => {
                const isPw = pwInput.type === 'password';
                pwInput.type = isPw ? 'text' : 'password';
                pwIcon.className = isPw ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            });
        }

        const themeBtn = document.getElementById('adminThemeBtn');
        const themeIcon = document.getElementById('adminThemeIcon');
        const syncThemeIcon = () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (themeIcon) themeIcon.className = isDark ? 'fa-regular fa-sun' : 'fa-regular fa-moon';
        };
        syncThemeIcon();
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                const cur = document.documentElement.getAttribute('data-theme') || 'dark';
                const next = cur === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);
                localStorage.setItem('portalTheme', next);
                syncThemeIcon();
            });
        }

        let adminLockoutInterval = null;
        function startAdminLockout(seconds) {
            const userInput = document.getElementById('adminUsername');
            const passInput = document.getElementById('adminPassword');
            const pwToggle = document.getElementById('adminPwToggle');
            const alertBox = document.getElementById('adminLoginAlert');
            const alertText = document.getElementById('adminAlertText');
            const submitBtn = document.getElementById('adminSubmitBtn');
            const submitText = document.getElementById('adminSubmitText');
            const submitIcon = document.getElementById('adminSubmitIcon');

            if (adminLockoutInterval) clearInterval(adminLockoutInterval);

            if (userInput) {
                userInput.disabled = true;
                userInput.setAttribute('readonly', 'readonly');
                userInput.style.opacity = '0.45';
                userInput.style.cursor = 'not-allowed';
                userInput.title = 'Inputs disabled due to security lockout.';
            }
            if (passInput) {
                passInput.disabled = true;
                passInput.setAttribute('readonly', 'readonly');
                passInput.style.opacity = '0.45';
                passInput.style.cursor = 'not-allowed';
                passInput.title = 'Inputs disabled due to security lockout.';
            }
            if (pwToggle) pwToggle.disabled = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.45';
                submitBtn.style.cursor = 'not-allowed';
                if (submitIcon) submitIcon.className = 'fa-solid fa-lock';
            }

            let remaining = Math.max(1, parseInt(seconds, 10) || 900);
            const targetExpiry = Date.now() + (remaining * 1000);
            localStorage.setItem('admin_login_lockout_until', targetExpiry.toString());

            function updateAdminLockoutUI() {
                const m = Math.floor(remaining / 60);
                const s = remaining % 60;
                const timeStr = `${m}:${s < 10 ? '0' : ''}${s}`;
                if (alertText) {
                    alertText.innerHTML = `<strong><i class="fa-solid fa-shield-halved"></i> Super Admin Gateway Locked:</strong> Multiple failed attempts detected. Text fields are disabled for: <strong>${timeStr}</strong>`;
                }
                if (alertBox) {
                    alertBox.style.display = 'flex';
                }
                if (submitText) submitText.textContent = `Locked (${timeStr})`;
            }

            updateAdminLockoutUI();

            adminLockoutInterval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(adminLockoutInterval);
                    adminLockoutInterval = null;
                    localStorage.removeItem('admin_login_lockout_until');

                    if (userInput) {
                        userInput.disabled = false;
                        userInput.removeAttribute('readonly');
                        userInput.style.opacity = '1';
                        userInput.style.cursor = 'text';
                        userInput.removeAttribute('title');
                        userInput.value = '';
                    }
                    if (passInput) {
                        passInput.disabled = false;
                        passInput.removeAttribute('readonly');
                        passInput.style.opacity = '1';
                        passInput.style.cursor = 'text';
                        passInput.removeAttribute('title');
                        passInput.value = '';
                    }
                    if (pwToggle) pwToggle.disabled = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                        submitBtn.style.cursor = 'pointer';
                        if (submitText) submitText.textContent = 'Authorize & Enter Console';
                        if (submitIcon) submitIcon.className = 'fa-solid fa-arrow-right-to-bracket';
                    }
                    if (alertText) {
                        alertText.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i> Cooldown expired. You may now attempt to sign in.';
                    }
                    if (userInput) userInput.focus();
                } else {
                    updateAdminLockoutUI();
                }
            }, 1000);
        }

        // Check if lockout is still active from localStorage (survives browser refresh!)
        (function checkStoredAdminLockout() {
            const stored = localStorage.getItem('admin_login_lockout_until');
            if (stored) {
                const expiry = parseInt(stored, 10);
                const remainingSec = Math.floor((expiry - Date.now()) / 1000);
                if (remainingSec > 0) {
                    startAdminLockout(remainingSec);
                    return;
                } else {
                    localStorage.removeItem('admin_login_lockout_until');
                }
            }

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('error') === 'locked' || urlParams.get('error') === 'ip_locked') {
                const waitMin = parseInt(urlParams.get('wait') || '15', 10);
                startAdminLockout(waitMin * 60);
            }
        })();

        const form = document.getElementById('superAdminLoginForm');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const alertBox = document.getElementById('adminLoginAlert');
                const alertText = document.getElementById('adminAlertText');
                const submitBtn = document.getElementById('adminSubmitBtn');
                const submitText = document.getElementById('adminSubmitText');
                const submitIcon = document.getElementById('adminSubmitIcon');

                if (alertBox) alertBox.style.display = 'none';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    if (submitText) submitText.textContent = 'Verifying Super Admin...';
                    if (submitIcon) submitIcon.className = 'fa-solid fa-spinner fa-spin';
                }

                const formData = new FormData(form);
                formData.append('ajax', '1');

                try {
                    const resp = await fetch('app/controllers/authenticate.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });
                    const data = await resp.json().catch(() => null);

                    if (data && data.success) {
                        if (submitText) submitText.textContent = 'Authorized! Redirecting...';
                        if (submitIcon) submitIcon.className = 'fa-solid fa-check';
                        submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                        submitBtn.style.color = '#ffffff';
                        setTimeout(() => {
                            window.location.href = data.redirect || 'dashboard.php';
                        }, 350);
                    } else {
                        const isLock = (data && (data.code === 'locked' || data.code === 'ip_locked')) || resp.status === 429;
                        if (isLock) {
                            const waitSec = (data && data.wait_seconds) ? data.wait_seconds : ((data && data.wait ? parseInt(data.wait, 10) : 15) * 60);
                            startAdminLockout(waitSec);
                            return;
                        }

                        const errorMsg = (data && data.error) ? data.error : 'Invalid Super Administrator credentials.';
                        if (alertBox && alertText) {
                            alertText.textContent = errorMsg;
                            alertBox.style.display = 'flex';
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            if (submitText) submitText.textContent = 'Authorize & Enter Console';
                            if (submitIcon) submitIcon.className = 'fa-solid fa-arrow-right-to-bracket';
                        }
                    }
                } catch (err) {
                    form.submit();
                }
            });
        }
    })();
</script>
</body>
</html>

