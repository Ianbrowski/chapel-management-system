<?php
/**
 * Public Password Reset Page
 * Verifies one-time emailed SHA-256 reset tokens and lets eligible active users set a new password.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
}

$auth = new AuthController($conn);
$csrf = $auth->generateCsrf();

$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawToken = trim((string)($method === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '')));
$errorMsg = '';

if ($method === 'POST') {
    $res = $auth->completePasswordReset();
    if (!empty($res['success'])) {
        header('Location: index.php?open_login=1&notice=password_reset_success');
        exit();
    }
    $errorMsg = (string)($res['error'] ?? 'Unable to reset password.');
}

$tokenData = $rawToken !== '' ? $auth->verifyResetToken($rawToken) : null;
$indexCssVer = @filemtime(__DIR__ . '/assets/css/index.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Reset Password — IFI Cathedral Portal</title>
    <link rel="icon" type="image/png" href="assets/img/2323.png">
    <script>
        (function () {
            const saved = localStorage.getItem('theme') || localStorage.getItem('portalTheme') || 'light';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css?v=<?php echo $indexCssVer; ?>">
    <style>
        .rp-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 16px;
            background: var(--bg);
        }
        .rp-card {
            width: min(94vw, 470px);
            background: var(--bg-card);
            border: 1px solid rgba(197, 160, 89, 0.4);
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.14);
            overflow: hidden;
        }
        .rp-body {
            padding: 28px 28px 26px;
        }
        .rp-account-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(197, 160, 89, 0.14);
            border: 1px solid rgba(197, 160, 89, 0.35);
            color: var(--gold, #c5a059);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .rp-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.86rem;
            line-height: 1.5;
            margin-bottom: 18px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #dc2626;
        }
        [data-theme="dark"] .rp-alert {
            color: #fca5a5;
        }
        .rp-match-hint {
            font-size: 0.78rem;
            margin-top: 6px;
            min-height: 18px;
        }
    </style>
</head>
<body>
<div class="rp-shell">
    <div class="rp-card">
        <div class="bmodal-header-accent"></div>
        <div class="bmodal-topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="bmodal-crest-wrap">
                    <img src="assets/img/2323.png" alt="IFI Crest" class="bmodal-crest">
                </div>
                <div>
                    <span class="bmodal-diocese">Cathedral Portal Security</span>
                    <span class="bmodal-parish">Iglesia Filipina Independiente</span>
                </div>
            </div>
        </div>

        <div class="rp-body">
            <?php if ($errorMsg !== ''): ?>
                <div class="rp-alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation" style="margin-top:2px;"></i>
                    <span><?php echo htmlspecialchars($errorMsg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$tokenData): ?>
                <div style="text-align:center; padding:10px 4px;">
                    <div style="width:62px; height:62px; margin:0 auto 14px; border-radius:50%; background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.3); display:flex; align-items:center; justify-content:center; color:#dc2626; font-size:1.5rem;">
                        <i class="fa-solid fa-link-slash"></i>
                    </div>
                    <h1 style="font-family:var(--font-family); font-size:1.3rem; font-weight:700; margin:0 0 8px; color:var(--text-main);">
                        Reset Link Invalid or Expired
                    </h1>
                    <p style="font-size:0.9rem; line-height:1.6; color:var(--text-dim); margin:0 0 22px;">
                        Password reset links expire after 15 minutes and can only be used once. Return to Sign In to request a fresh reset link.
                    </p>
                    <a href="index.php?open_login=1" class="btn-modal-login-submit" style="text-decoration:none; justify-content:center;">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back to Portal Sign In</span>
                    </a>
                </div>
            <?php else: ?>
                <div style="text-align:center;">
                    <div class="rp-account-pill">
                        <i class="fa-solid fa-user-shield"></i>
                        <span><?php echo htmlspecialchars($tokenData['user']); ?> &bull; <?php echo htmlspecialchars($tokenData['role']); ?></span>
                    </div>
                    <h1 style="font-family:var(--font-family); font-size:1.35rem; font-weight:700; margin:0 0 6px; color:var(--text-main);">
                        Create New Password
                    </h1>
                    <p style="font-size:0.86rem; color:var(--text-dim); margin:0 0 20px;">
                        Choose a strong password (6 to 72 characters).
                    </p>
                </div>

                <form method="POST" action="reset_password.php" id="resetPasswordForm">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($rawToken); ?>">

                    <div class="modal-form-group">
                        <label for="rpNewPassword" class="modal-form-label">New Password</label>
                        <div class="modal-input-wrap">
                            <i class="fa-solid fa-lock modal-input-icon"></i>
                            <input type="password" id="rpNewPassword" name="new_password" class="modal-form-input"
                                   placeholder="Minimum 6 characters" minlength="6" maxlength="72" required autocomplete="new-password">
                            <button type="button" class="modal-pw-toggle" onclick="toggleRpField('rpNewPassword', 'rpEye1')" aria-label="Toggle new password visibility">
                                <i class="fa-regular fa-eye" id="rpEye1"></i>
                            </button>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label for="rpConfirmPassword" class="modal-form-label">Confirm New Password</label>
                        <div class="modal-input-wrap">
                            <i class="fa-solid fa-check-double modal-input-icon"></i>
                            <input type="password" id="rpConfirmPassword" name="confirm_password" class="modal-form-input"
                                   placeholder="Re-enter new password" minlength="6" maxlength="72" required autocomplete="new-password"
                                   oninput="checkRpMatch()">
                            <button type="button" class="modal-pw-toggle" onclick="toggleRpField('rpConfirmPassword', 'rpEye2')" aria-label="Toggle confirm password visibility">
                                <i class="fa-regular fa-eye" id="rpEye2"></i>
                            </button>
                        </div>
                        <div id="rpMatchHint" class="rp-match-hint"></div>
                    </div>

                    <button type="submit" id="rpSubmitBtn" class="btn-modal-login-submit">
                        <span>Save New Password</span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </form>

                <div style="text-align:center; margin-top:16px;">
                    <a href="index.php?open_login=1" style="font-size:0.84rem; color:var(--text-dim); text-decoration:none; font-weight:600;">
                        <i class="fa-solid fa-arrow-left"></i> Return to Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleRpField(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if (!inp || !ico) return;
    const hidden = inp.type === 'password';
    inp.type = hidden ? 'text' : 'password';
    ico.className = hidden ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
}

function checkRpMatch() {
    const p1 = document.getElementById('rpNewPassword');
    const p2 = document.getElementById('rpConfirmPassword');
    const hint = document.getElementById('rpMatchHint');
    const btn = document.getElementById('rpSubmitBtn');
    if (!p1 || !p2 || !hint) return;
    if (!p2.value) {
        hint.textContent = '';
        if (btn) btn.disabled = false;
        return;
    }
    if (p1.value === p2.value) {
        hint.textContent = 'Passwords match';
        hint.style.color = '#10b981';
        if (btn) btn.disabled = false;
    } else {
        hint.textContent = 'Passwords do not match yet';
        hint.style.color = '#ef4444';
        if (btn) btn.disabled = true;
    }
}
</script>
</body>
</html>
