<?php
declare(strict_types=1);

// Prevent direct access without bootstrap
if (!isset($authView)) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &bull; ReliefFlow Inventory Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js"></script>
    <style>
        :root {
            --bg-outer-gradient: linear-gradient(135deg, #CDB4C8 0%, #D8C2D4 50%, #C4AABF 100%);
            --container-cream: #F5EDE3;
            --panel-plum: #9B7A94;
            --panel-plum-card: #85617E;
            --text-dark: #3E2938;
            --text-muted: #7A6273;
            --radius-container: 28px;
            --radius-card: 22px;
            --radius-pill: 9999px;
            --shadow-soft: 0 16px 40px rgba(95, 63, 89, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            background: var(--bg-outer-gradient);
            min-height: 100vh;
            width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', sans-serif;
            color: var(--text-dark);
            margin: 0;
            padding: 20px;
        }
        .login-frame {
            width: 100%;
            max-width: 440px;
            background: var(--container-cream);
            border-radius: var(--radius-container);
            padding: 40px 32px;
            box-shadow: var(--shadow-soft);
        }
        .brand-pill-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .brand-logo-badge {
            width: 46px;
            height: 46px;
            background: var(--panel-plum);
            color: #ffffff;
            border-radius: 16px;
            display: grid;
            place-items: center;
        }
        .brand-title {
            font-size: 24px;
            font-weight: 900;
            color: var(--text-dark);
            letter-spacing: -0.02em;
            margin: 0;
        }
        .auth-nav-pill-group {
            display: flex;
            background: #E8DCD1;
            border-radius: var(--radius-pill);
            padding: 4px;
            margin-bottom: 24px;
        }
        .auth-nav-tab {
            flex: 1;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-muted);
            border: 0;
            background: transparent;
            border-radius: var(--radius-pill);
            cursor: pointer;
            transition: all 0.15s ease;
            min-height: 44px;
        }
        .auth-nav-tab.active {
            background: var(--container-cream);
            color: var(--text-dark);
            box-shadow: 0 2px 6px rgba(95, 63, 89, 0.08);
        }
        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .form-control {
            border: 1px solid #DFD0C3;
            background: #FAF5F0;
            border-radius: var(--radius-pill);
            padding: 11px 18px;
            font-size: 14px;
            color: var(--text-dark);
            min-height: 44px;
        }
        .form-control:focus {
            background: #ffffff;
            border-color: var(--panel-plum);
            box-shadow: 0 0 0 3px rgba(155, 122, 148, 0.2);
            outline: none;
        }
        .input-group-pill {
            display: flex;
            position: relative;
        }
        .input-group-pill .form-control {
            padding-right: 48px;
        }
        .btn-toggle-eye {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: var(--text-muted);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            cursor: pointer;
        }
        .btn-plum-pill {
            background: var(--panel-plum);
            color: #ffffff;
            font-weight: 800;
            font-size: 15px;
            border: 0;
            border-radius: var(--radius-pill);
            padding: 12px 24px;
            width: 100%;
            min-height: 46px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s;
        }
        .btn-plum-pill:hover {
            background: #84617E;
            color: #ffffff;
        }
        .demo-pill-card {
            background: #EBE0DB;
            border-radius: var(--radius-card);
            padding: 14px 16px;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-dark);
        }
        .auth-section { display: none; }
        .auth-section.active { display: block; }
    </style>
</head>
<body>

<div class="login-frame">
    <div class="brand-pill-header">
        <div class="brand-logo-badge">
            <i data-lucide="package-search" style="width:24px;height:24px;stroke-width:2;"></i>
        </div>
        <div>
            <h1 class="brand-title">ReliefFlow</h1>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-muted);">Disaster Supply Logistics</div>
        </div>
    </div>

    <?php if ($forgotNotice): ?>
        <div class="alert alert-info py-2 small rounded-4 mb-3"><?=h($forgotNotice)?></div>
    <?php endif; ?>

    <div class="auth-nav-pill-group">
        <button type="button" class="auth-nav-tab <?=$authView === 'login' ? 'active' : ''?>" id="tabLogin" onclick="switchAuth('login')">Staff Sign In</button>
        <button type="button" class="auth-nav-tab <?=$authView === 'register' ? 'active' : ''?>" id="tabRegister" onclick="switchAuth('register')">Register Account</button>
    </div>

    <!-- Login Panel -->
    <div class="auth-section <?=$authView === 'login' ? 'active' : ''?>" id="sectLogin">
        <?php if ($loginError): ?>
            <div class="alert alert-danger py-2 small rounded-4 mb-3"><?=h($loginError)?></div>
        <?php endif; ?>

        <form method="post" action="index.php" novalidate>
            <input type="hidden" name="action" value="login">

            <div class="mb-3">
                <label class="form-label" for="login_user">Username or Email</label>
                <input class="form-control" type="text" id="login_user" name="username" placeholder="e.g. admin or staff@reliefcare.org" required autofocus autocomplete="username">
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label m-0" for="login_pass">Password</label>
                    <button type="button" class="btn btn-link p-0 text-decoration-none" style="font-size: 12px; font-weight: 700; color: var(--panel-plum);" data-bs-toggle="modal" data-bs-target="#modalForgotPass">Forgot password?</button>
                </div>
                <div class="input-group-pill">
                    <input class="form-control" type="password" id="login_pass" name="password" placeholder="Enter password" required autocomplete="current-password">
                    <button type="button" class="btn-toggle-eye" onclick="togglePass('login_pass', this)" aria-label="Toggle password view">
                        <i data-lucide="eye" style="width:18px;height:18px;stroke-width:2;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-plum-pill mt-2">
                Sign In to Relief Hub
            </button>
        </form>

        <div class="demo-pill-card">
            <div class="fw-bold mb-1">Default Credentials</div>
            <div>Username: <strong style="color: var(--panel-plum);">admin</strong></div>
            <div>Password: <strong style="color: var(--panel-plum);">admin123</strong></div>
        </div>
    </div>

    <!-- Register Panel -->
    <div class="auth-section <?=$authView === 'register' ? 'active' : ''?>" id="sectRegister">
        <?php if ($registerError): ?>
            <div class="alert alert-danger py-2 small rounded-4 mb-3"><?=h($registerError)?></div>
        <?php endif; ?>
        <?php if ($registerSuccess): ?>
            <div class="alert alert-success py-2 small rounded-4 mb-3"><?=h($registerSuccess)?></div>
        <?php endif; ?>

        <form method="post" action="index.php" novalidate>
            <input type="hidden" name="action" value="register">

            <div class="mb-3">
                <label class="form-label" for="reg_user">Username</label>
                <input class="form-control" type="text" id="reg_user" name="reg_username" placeholder="e.g. j_vol" maxlength="45" required value="<?=h($_POST['reg_username'] ?? '')?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="reg_email">Email Address</label>
                <input class="form-control" type="email" id="reg_email" name="reg_email" placeholder="e.g. volunteer@reliefcare.org" maxlength="100" required value="<?=h($_POST['reg_email'] ?? '')?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="reg_pass">Password (min. 6 characters)</label>
                <div class="input-group-pill">
                    <input class="form-control" type="password" id="reg_pass" name="reg_password" minlength="6" placeholder="Create password" required>
                    <button type="button" class="btn-toggle-eye" onclick="togglePass('reg_pass', this)" aria-label="Toggle password view">
                        <i data-lucide="eye" style="width:18px;height:18px;stroke-width:2;"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="reg_conf">Confirm Password</label>
                <div class="input-group-pill">
                    <input class="form-control" type="password" id="reg_conf" name="reg_confirm_password" minlength="6" placeholder="Repeat password" required>
                    <button type="button" class="btn-toggle-eye" onclick="togglePass('reg_conf', this)" aria-label="Toggle password view">
                        <i data-lucide="eye" style="width:18px;height:18px;stroke-width:2;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-plum-pill">
                Create Relief Account
            </button>
        </form>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="modalForgotPass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="forgot_password">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Password Assistance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p style="font-size: 14px; color: var(--text-muted);" class="mb-3">Provide your registered username or organization email. Reset verification instructions will be routed to your relief logistics administrator.</p>
                    <label class="form-label" for="forgot_in">Username or Email</label>
                    <input class="form-control" type="text" id="forgot_in" name="forgot_input" placeholder="e.g. admin or staff@reliefcare.org" required>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-pill" style="width: auto; padding: 10px 22px;">Request Assistance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();
function switchAuth(tab) {
    document.getElementById('tabLogin').classList.toggle('active', tab === 'login');
    document.getElementById('tabRegister').classList.toggle('active', tab === 'register');
    document.getElementById('sectLogin').classList.toggle('active', tab === 'login');
    document.getElementById('sectRegister').classList.toggle('active', tab === 'register');
}
function togglePass(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const isPw = input.type === 'password';
    input.type = isPw ? 'text' : 'password';
    btn.innerHTML = isPw ? '<i data-lucide="eye-off" style="width:18px;height:18px;stroke-width:2;"></i>' : '<i data-lucide="eye" style="width:18px;height:18px;stroke-width:2;"></i>';
    lucide.createIcons();
}
</script>
</body>
</html>
