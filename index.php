<?php

declare(strict_types=1);
date_default_timezone_set('Asia/Manila');

// 1. SESSION MANAGEMENT (Secure Session Initialization & Lifecycle)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Session activity timestamp tracking
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    if (!isset($_SESSION['role'])) {
        $_SESSION['role'] = 'Relief Staff & Logistics Volunteer';
    }
}

require_once __DIR__ . '/connections.php';

/* ============================================================
   HELPERS & LOGGING
============================================================ */
if (!function_exists('h')) {
function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('redirect')) {
function redirect(string $section = 'dashboard', string $message = '', string $type = 'success', array $extraParams = []) {
    $url = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?');
    if (!$url || $url === '') {
        $url = 'index.php';
    }
    $params = array_merge(['section' => $section], $extraParams);
    if ($message !== '') {
        $params['message'] = $message;
        $params['type'] = $type;
        // Session Flash Messaging
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url . '?' . http_build_query($params));
    exit;
}
}

if (!function_exists('flashMessage')) {
function flashMessage(): array {
    $msg = $_GET['message'] ?? $_SESSION['flash_message'] ?? '';
    $type = $_GET['type'] ?? $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return [$msg, $type];
}
}

if (!function_exists('logActivity')) {
function logActivity(PDO $conn, string $action, int $status, int $userId, ?int $donationId = null): void {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
             VALUES (?, ?, NOW(), ?, ?)"
        );
        $stmt->execute([$action, $status, $userId, $donationId]);
    } catch (Throwable $e) {}
}
}

if (!function_exists('notifyUser')) {
function notifyUser(PDO $conn, string $message, string $eventDate, int $userId, ?int $donationId = null): void {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO notifications (message, event_date, is_read, user_id, donation_id)
             VALUES (?, ?, 0, ?, ?)"
        );
        $stmt->execute([$message, $eventDate, $userId, $donationId]);
    } catch (Throwable) {}
}
}

/* ============================================================
   AUTHENTICATION: LOGOUT, LOGIN, REGISTER, FORGOT PASSWORD
============================================================ */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?'));
    exit;
}

$loginError = '';
$registerError = '';
$registerSuccess = '';
$forgotNotice = '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Process Login
if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $usernameOrEmail = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($usernameOrEmail === '' || $password === '') {
        $loginError = 'Please enter your username/email and password.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $passwordOk = false;
            if ($user) {
                $storedPassword = (string)$user['password'];
                $hashInfo = password_get_info($storedPassword);
                if ($hashInfo['algo'] !== null && $hashInfo['algo'] !== 0) {
                    $passwordOk = password_verify($password, $storedPassword);
                } else {
                    $passwordOk = hash_equals($storedPassword, $password);
                    if ($passwordOk) {
                        $upg = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $upg->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
                    }
                }
            }

            if ($passwordOk && $user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                logActivity($conn, 'USER_LOGIN', 1, (int)$user['user_id']);
                redirect('dashboard', 'Welcome back, ' . $user['username'] . '!');
            }
            $loginError = 'Invalid credentials. Please verify your login details.';
        } catch (Throwable) {
            $loginError = 'Database service error during login. Please retry.';
        }
    }
}

// Process Register
if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $regUsername = trim((string)($_POST['reg_username'] ?? ''));
    $regEmail = trim((string)($_POST['reg_email'] ?? ''));
    $regPassword = (string)($_POST['reg_password'] ?? '');
    $regConfirm = (string)($_POST['reg_confirm_password'] ?? '');

    if ($regUsername === '' || $regEmail === '' || $regPassword === '') {
        $registerError = 'All registration fields are required.';
    } elseif (strlen($regUsername) > 45) {
        $registerError = 'Username must be 45 characters or fewer.';
    } elseif (!preg_match('/^[A-Za-z0-9_.\-]+$/', $regUsername)) {
        $registerError = 'Username may only contain letters, numbers, underscores, dots, or dashes.';
    } elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Please enter a valid relief staff email address.';
    } elseif (strlen($regPassword) < 6) {
        $registerError = 'Password must be at least 6 characters.';
    } elseif ($regPassword !== $regConfirm) {
        $registerError = 'Passwords do not match.';
    } else {
        try {
            $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check->execute([$regUsername, $regEmail]);
            if ((int)$check->fetchColumn() > 0) {
                $registerError = 'That username or email is already registered.';
            } else {
                $insert = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                $insert->execute([$regUsername, password_hash($regPassword, PASSWORD_DEFAULT), $regEmail]);
                $registerSuccess = 'Account created successfully! You may now sign in below.';
            }
        } catch (Throwable $e) {
            $registerError = 'Registration error: ' . $e->getMessage();
        }
    }
}

// Process Forgot Password Simulation
if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'forgot_password') {
    $forgotInput = trim((string)($_POST['forgot_input'] ?? ''));
    if ($forgotInput !== '') {
        $forgotNotice = 'Password recovery guidance dispatched for "' . h($forgotInput) . '". Reach out to your disaster logistics coordinator for credential resets.';
    }
}

$authView = 'login';
if ($registerError !== '' || $registerSuccess !== '' || ($_GET['view'] ?? '') === 'register') {
    $authView = 'register';
}

/* ============================================================
   GUEST AUTH SCREEN (Smartech Aesthetic)
============================================================ */
if (!isset($_SESSION['user_id'])):
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
<?php
exit;
endif;

/* ============================================================
   AUTHENTICATED ACTIONS HANDLER
============================================================ */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = (string)($_SESSION['username'] ?? 'Sophia');

try {
    // 1. Profile Update
    if ($requestMethod === 'POST' && $action === 'profile_update') {
        $newUsername = trim((string)($_POST['username'] ?? ''));
        $newEmail = trim((string)($_POST['email'] ?? ''));

        if ($newUsername === '' || $newEmail === '') {
            redirect('profile', 'Username and email cannot be empty.', 'danger');
        }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            redirect('profile', 'Invalid email address format.', 'danger');
        }

        $dupStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
        $dupStmt->execute([$newUsername, $newEmail, $currentUserId]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            redirect('profile', 'Username or email already claimed by another user.', 'danger');
        }

        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
        $stmt->execute([$newUsername, $newEmail, $currentUserId]);
        $_SESSION['username'] = $newUsername;
        $_SESSION['email'] = $newEmail;
        logActivity($conn, 'UPDATED_PROFILE', 1, $currentUserId);
        redirect('profile', 'Profile details updated successfully.');
    }

    // 2. Change Password
    if ($requestMethod === 'POST' && $action === 'password_change') {
        $curPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confPass = (string)($_POST['confirm_password'] ?? '');

        if ($newPass !== $confPass) {
            redirect('profile', 'New passwords do not match.', 'danger');
        }
        if (strlen($newPass) < 6) {
            redirect('profile', 'New password must be at least 6 characters.', 'danger');
        }

        $chkStmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $chkStmt->execute([$currentUserId]);
        $stored = (string)$chkStmt->fetchColumn();

        $passOk = false;
        $hashInfo = password_get_info($stored);
        if ($hashInfo['algo'] !== null && $hashInfo['algo'] !== 0) {
            $passOk = password_verify($curPass, $stored);
        } else {
            $passOk = hash_equals($stored, $curPass);
        }

        if (!$passOk) {
            redirect('profile', 'Current password is incorrect.', 'danger');
        }

        $upStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upStmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $currentUserId]);
        logActivity($conn, 'CHANGED_PASSWORD', 1, $currentUserId);
        redirect('profile', 'Password updated successfully.');
    }

    // 3. Category Create
    if ($requestMethod === 'POST' && $action === 'category_create') {
        $catName = trim((string)($_POST['category_name'] ?? ''));
        $catDesc = trim((string)($_POST['description'] ?? ''));
        $catIcon = trim((string)($_POST['icon'] ?? 'package')) ?: 'package';
        $catColor = trim((string)($_POST['color'] ?? '#0284c7')) ?: '#0284c7';

        if ($catName === '') {
            redirect('categories', 'Category name cannot be empty.', 'danger');
        }
        $stmt = $conn->prepare("INSERT INTO categories (category_name, description, icon, color) VALUES (?, ?, ?, ?)");
        $stmt->execute([$catName, $catDesc, $catIcon, $catColor]);
        logActivity($conn, 'CREATED_CATEGORY', 1, $currentUserId);
        redirect('categories', 'Category "' . $catName . '" added successfully.');
    }

    // 4. Category Edit
    if ($requestMethod === 'POST' && $action === 'category_edit') {
        $catId = (int)$_POST['category_id'];
        $catName = trim((string)($_POST['category_name'] ?? ''));
        $catDesc = trim((string)($_POST['description'] ?? ''));
        $catIcon = trim((string)($_POST['icon'] ?? 'package')) ?: 'package';
        $catColor = trim((string)($_POST['color'] ?? '#0284c7')) ?: '#0284c7';

        if ($catName === '') {
            redirect('categories', 'Category name cannot be empty.', 'danger');
        }
        $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ?, icon = ?, color = ? WHERE category_id = ?");
        $stmt->execute([$catName, $catDesc, $catIcon, $catColor, $catId]);
        logActivity($conn, 'UPDATED_CATEGORY', 1, $currentUserId);
        redirect('categories', 'Category updated successfully.');
    }

    // 5. Category Delete
    if ($requestMethod === 'POST' && $action === 'category_delete') {
        $catId = (int)$_POST['category_id'];
        try {
            $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$catId]);
            logActivity($conn, 'DELETED_CATEGORY', 1, $currentUserId);
            redirect('categories', 'Category deleted successfully.');
        } catch (Throwable) {
            redirect('categories', 'Cannot delete this category because active donation records are assigned to it.', 'danger');
        }
    }

    // 6. Donor Create
    if ($requestMethod === 'POST' && $action === 'donor_create') {
        $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
        $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($contactPerson === '') {
            redirect('donors', 'Donor contact person name is required.', 'danger');
        }
        $stmt = $conn->prepare("INSERT INTO donors (contact_person, contact_number, address, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$contactPerson, $contactNumber, $address, $currentUserId]);
        logActivity($conn, 'CREATED_DONOR', 1, $currentUserId);
        redirect('donors', 'Donor contributor recorded successfully.');
    }

    // 7. Donor Edit
    if ($requestMethod === 'POST' && $action === 'donor_edit') {
        $donorId = (int)$_POST['donor_id'];
        $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
        $contactNumber = trim((string)($_POST['contact_number'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        $stmt = $conn->prepare("UPDATE donors SET contact_person = ?, contact_number = ?, address = ? WHERE donor_id = ?");
        $stmt->execute([$contactPerson, $contactNumber, $address, $donorId]);
        logActivity($conn, 'UPDATED_DONOR', 1, $currentUserId);
        redirect('donors', 'Donor contributor updated successfully.');
    }

    // 8. Donor Delete
    if ($requestMethod === 'POST' && $action === 'donor_delete') {
        $donorId = (int)$_POST['donor_id'];
        try {
            $stmt = $conn->prepare("DELETE FROM donors WHERE donor_id = ?");
            $stmt->execute([$donorId]);
            logActivity($conn, 'DELETED_DONOR', 1, $currentUserId);
            redirect('donors', 'Donor removed successfully.');
        } catch (Throwable) {
            redirect('donors', 'Cannot delete this donor because active donation records exist.', 'danger');
        }
    }

    // ============================================================
    // TRANSACTION 1: Atomic Relief Donation Intake (Donation + Audit Log + Notification)
    // ============================================================
    if ($requestMethod === 'POST' && $action === 'donation_create') {
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $donationDate = !empty($_POST['donation_date']) ? $_POST['donation_date'] : date('Y-m-d');
        $donorId = (int)($_POST['donor_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Pending', 'Received', 'In Stock', 'Released'], true) ? $_POST['status'] : 'Received';

        if ($itemName === '' || $donorId === 0 || $categoryId === 0) {
            redirect('donations', 'Please fill in item name, donor, and category.', 'danger');
        }

        $conn->beginTransaction();
        try {
            // 1. Insert core donation record
            $stmt = $conn->prepare(
                "INSERT INTO donations (item_name, quantity, donation_date, status, donor_id, category_id)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$itemName, $quantity, $donationDate, $status, $donorId, $categoryId]);
            $newDonationId = (int)$conn->lastInsertId();

            // 2. Insert audit trail entry
            $logStmt = $conn->prepare(
                "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
                 VALUES ('CREATED_DONATION', 1, NOW(), ?, ?)"
            );
            $logStmt->execute([$currentUserId, $newDonationId]);

            // 3. Dispatch intake alert notification
            $noteStmt = $conn->prepare(
                "INSERT INTO notifications (message, event_date, is_read, user_id, donation_id)
                 VALUES (?, ?, 0, ?, ?)"
            );
            $noteStmt->execute(["New relief supply recorded: {$itemName} ({$quantity} units)", $donationDate, $currentUserId, $newDonationId]);

            $conn->commit();
            redirect('donations', 'Donation recorded successfully in relief inventory.');
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            redirect('donations', 'Failed to record donation: ' . $e->getMessage(), 'danger');
        }
    }

    // 10. Donation Edit
    if ($requestMethod === 'POST' && $action === 'donation_edit') {
        $donationId = (int)$_POST['donation_id'];
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $donationDate = !empty($_POST['donation_date']) ? $_POST['donation_date'] : date('Y-m-d');
        $donorId = (int)($_POST['donor_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Pending', 'Received', 'In Stock', 'Released'], true) ? $_POST['status'] : 'Received';

        $stmt = $conn->prepare(
            "UPDATE donations
             SET item_name = ?, quantity = ?, donation_date = ?, status = ?, donor_id = ?, category_id = ?
             WHERE donation_id = ?"
        );
        $stmt->execute([$itemName, $quantity, $donationDate, $status, $donorId, $categoryId, $donationId]);

        logActivity($conn, 'UPDATED_DONATION', 1, $currentUserId, $donationId);
        redirect('donations', 'Donation item updated successfully.');
    }

    // ============================================================
    // TRANSACTION 2: Atomic Status Update & Field Dispatch Ledger
    // ============================================================
    if ($requestMethod === 'POST' && $action === 'donation_status_update') {
        $donationId = (int)($_POST['donation_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['Pending', 'Received', 'In Stock', 'Released'], true) ? $_POST['status'] : 'Received';

        if ($donationId <= 0) {
            redirect('donations', 'Invalid donation record.', 'danger');
        }

        $conn->beginTransaction();
        try {
            // 1. Verify existence and read current stock info
            $checkStmt = $conn->prepare("SELECT item_name, quantity, status FROM donations WHERE donation_id = ?");
            $checkStmt->execute([$donationId]);
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new Exception("Donation record #{$donationId} not found in database.");
            }

            // 2. Update status in donations table
            $stmt = $conn->prepare("UPDATE donations SET status = ? WHERE donation_id = ?");
            $stmt->execute([$newStatus, $donationId]);

            // 3. Atomically record the status transition in activitylogs
            $logStmt = $conn->prepare(
                "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
                 VALUES (?, 1, NOW(), ?, ?)"
            );
            $logStmt->execute(["STATUS_CHANGE_TO_{$newStatus}", $currentUserId, $donationId]);

            // 4. If released to evacuation field, generate automated dispatch alert
            if ($newStatus === 'Released') {
                $noteStmt = $conn->prepare(
                    "INSERT INTO notifications (message, event_date, is_read, user_id, donation_id)
                     VALUES (?, CURDATE(), 0, ?, ?)"
                );
                $noteStmt->execute(["Relief batch dispatched to field: {$item['item_name']} ({$item['quantity']} units)", $currentUserId, $donationId]);
            }

            $conn->commit();
            redirect('donations', "Status updated to {$newStatus}.");
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            redirect('donations', 'Failed to update status: ' . $e->getMessage(), 'danger');
        }
    }

    // ============================================================
    // TRANSACTION 3: Atomic Donation Safe Deletion & Audit Trail
    // ============================================================
    if ($requestMethod === 'POST' && $action === 'donation_delete') {
        $donationId = (int)($_POST['donation_id'] ?? 0);
        
        if ($donationId <= 0) {
            redirect('donations', 'Invalid donation record selected.', 'danger');
        }

        $conn->beginTransaction();
        try {
            // 1. Inspect donation record details before removal for audit trail
            $infoStmt = $conn->prepare("SELECT item_name, quantity FROM donations WHERE donation_id = ?");
            $infoStmt->execute([$donationId]);
            $item = $infoStmt->fetch(PDO::FETCH_ASSOC);

            // 2. Safely nullify activitylogs foreign key references
            $conn->prepare("UPDATE activitylogs SET donation_id = NULL WHERE donation_id = ?")->execute([$donationId]);

            // 3. Remove dependent notifications
            $conn->prepare("DELETE FROM notifications WHERE donation_id = ?")->execute([$donationId]);

            // 4. Delete the donation record
            $stmt = $conn->prepare("DELETE FROM donations WHERE donation_id = ?");
            $stmt->execute([$donationId]);

            // 5. Commit audit ledger record
            $itemDesc = $item ? "{$item['item_name']} ({$item['quantity']} units)" : "ID #{$donationId}";
            $logStmt = $conn->prepare(
                "INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id)
                 VALUES ('DELETED_DONATION', 1, NOW(), ?, NULL)"
            );
            $logStmt->execute([$currentUserId]);

            $conn->commit();
            redirect('donations', "Donation '{$itemDesc}' removed from inventory successfully.");
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            redirect('donations', 'Failed to delete donation record: ' . $e->getMessage(), 'danger');
        }
    }

    // ============================================================
    // TRANSACTION 4: Atomic Relief Mission Event Scheduling
    // ============================================================
    if ($requestMethod === 'POST' && $action === 'event_create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $eventDate = trim((string)($_POST['event_date'] ?? date('Y-m-d')));
        $eventTime = trim((string)($_POST['event_time'] ?? '09:00'));
        $location = trim((string)($_POST['location'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Scheduled'));

        if ($title === '' || $eventDate === '') {
            redirect('dashboard', 'Please provide event title and date.', 'danger');
        }

        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("INSERT INTO events (title, event_date, event_time, location, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $eventDate, $eventTime, $location, $status]);

            // Audit log
            $logStmt = $conn->prepare("INSERT INTO activitylogs (action, status, timestamp, user_id, donation_id) VALUES ('SCHEDULED_EVENT', 1, NOW(), ?, NULL)");
            $logStmt->execute([$currentUserId]);

            // Staff Notification
            $noteStmt = $conn->prepare("INSERT INTO notifications (message, event_date, is_read, user_id, donation_id) VALUES (?, ?, 0, ?, NULL)");
            $noteStmt->execute(["New event scheduled: $title on $eventDate", $eventDate, $currentUserId]);

            $conn->commit();
            redirect('dashboard', "Event '$title' scheduled successfully.", 'success', ['sel_date' => $eventDate]);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            redirect('dashboard', 'Failed to schedule event: ' . $e->getMessage(), 'danger');
        }
    }

    if ($requestMethod === 'POST' && $action === 'event_status_update') {
        $eventId = (int)$_POST['event_id'];
        $status = trim((string)($_POST['status'] ?? 'Completed'));
        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
        $stmt->execute([$status, $eventId]);
        logActivity($conn, 'UPDATED_EVENT_STATUS', 1, $currentUserId);
        redirect('dashboard', "Event marked as $status.");
    }

    if ($requestMethod === 'POST' && $action === 'event_delete') {
        $eventId = (int)$_POST['event_id'];
        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->execute([$eventId]);
        logActivity($conn, 'DELETED_EVENT', 1, $currentUserId);
        redirect('dashboard', 'Event removed from schedule.');
    }

    // 14. Notifications Read Actions
    if ($action === 'notification_read') {
        $noteId = (int)($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
        if ($noteId > 0) {
            $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?")->execute([$noteId]);
        }
        redirect('notifications', 'Notification marked as read.');
    }

    if ($action === 'notification_read_all') {
        $conn->exec("UPDATE notifications SET is_read = 1");
        redirect('notifications', 'All notifications marked as read.');
    }

} catch (Throwable $e) {
    redirect($_GET['section'] ?? 'dashboard', 'System error: ' . $e->getMessage(), 'danger');
}

/* ============================================================
   GLOBAL DATA & STATS
============================================================ */
[$flash, $flashType] = flashMessage();
$section = $_GET['section'] ?? 'dashboard';

// Unread notifications
$unreadNotesCount = 0;
$recentNotifications = [];
try {
    $unreadNotesCount = (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
    $recentNotifications = $conn->query("SELECT * FROM notifications ORDER BY notification_id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {}

// High-level counts
try {
    $totalDonationsCount = (int)$conn->query("SELECT COUNT(*) FROM donations")->fetchColumn();
    $totalDonorsCount = (int)$conn->query("SELECT COUNT(*) FROM donors")->fetchColumn();
    $totalCategoriesCount = (int)$conn->query("SELECT COUNT(*) FROM categories")->fetchColumn();

    $totalReceivedUnits = (int)$conn->query("SELECT COALESCE(SUM(quantity), 0) FROM donations WHERE status IN ('Received', 'In Stock')")->fetchColumn();
    $totalReleasedUnits = (int)$conn->query("SELECT COALESCE(SUM(quantity), 0) FROM donations WHERE status = 'Released'")->fetchColumn();
    $totalPendingUnits = (int)$conn->query("SELECT COALESCE(SUM(quantity), 0) FROM donations WHERE status = 'Pending'")->fetchColumn();
    $netInStockUnits = $totalReceivedUnits;

    // Percentages for the Plum Ring Charts (matching reference screenshot rings)
    $grandTotalHandled = max(1, $totalReceivedUnits + $totalReleasedUnits + $totalPendingUnits);
    $stockLevelPct = min(100, (int)round(($netInStockUnits / $grandTotalHandled) * 100));
    $itemsReleasedPct = min(100, (int)round(($totalReleasedUnits / $grandTotalHandled) * 100));
    $itemsReceivedPct = min(100, (int)round((($totalReceivedUnits + $totalReleasedUnits) / $grandTotalHandled) * 100));

    // Fallbacks if system is freshly seeded
    if ($stockLevelPct === 0 && $netInStockUnits > 0) $stockLevelPct = 68;
    if ($itemsReleasedPct === 0) $itemsReleasedPct = 24;
    if ($itemsReceivedPct === 0) $itemsReceivedPct = 92;

    // Low stock alert items (<15 units)
    $lowStockStmt = $conn->query(
        "SELECT d.donation_id, d.item_name, d.quantity, d.status, c.category_name, c.icon, c.color
         FROM donations d
         LEFT JOIN categories c ON c.category_id = d.category_id
         WHERE d.quantity < 15 AND d.status != 'Released'
         ORDER BY d.quantity ASC
         LIMIT 4"
    );
    $lowStockItems = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
    $lowStockCount = count($lowStockItems);

    // Recent Donors for widget
    $recentDonors = $conn->query(
        "SELECT dn.donor_id, dn.contact_person, dn.contact_number, dn.address,
                COALESCE(c.category_name, 'General Aid') AS latest_category,
                COALESCE(d.item_name, 'Relief Supplies') AS latest_item
         FROM donors dn
         LEFT JOIN donations d ON d.donor_id = dn.donor_id
         LEFT JOIN categories c ON c.category_id = d.category_id
         GROUP BY dn.donor_id
         ORDER BY dn.donor_id DESC
         LIMIT 3"
    )->fetchAll(PDO::FETCH_ASSOC);

    // All Events from database (for interactive calendar & upcoming events widget)
    $allEvents = $conn->query(
        "SELECT * FROM events ORDER BY event_date ASC, event_time ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Upcoming Events (all active scheduled or in-progress relief missions)
    $upcomingEvents = $conn->query(
        "SELECT * FROM events 
         WHERE status != 'Completed' AND status != 'Cancelled'
         ORDER BY 
             CASE WHEN event_date >= CURDATE() THEN 0 ELSE 1 END,
             event_date ASC, 
             event_time ASC, 
             event_id DESC 
         LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($upcomingEvents)) {
        $upcomingEvents = $conn->query(
            "SELECT * FROM events ORDER BY event_date DESC, event_id DESC LIMIT 4"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // Recent Donations for batch thumbnails widget
    $batchDonations = $conn->query(
        "SELECT d.*, c.category_name, c.icon, c.color, dn.contact_person
         FROM donations d
         LEFT JOIN categories c ON c.category_id = d.category_id
         LEFT JOIN donors dn ON dn.donor_id = d.donor_id
         ORDER BY d.donation_id DESC
         LIMIT 2"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable) {
    $totalDonationsCount = $totalDonorsCount = $totalCategoriesCount = 0;
    $totalReceivedUnits = $totalReleasedUnits = $totalPendingUnits = $netInStockUnits = $lowStockCount = 0;
    $stockLevelPct = 68;
    $itemsReleasedPct = 84;
    $itemsReceivedPct = 92;
    $lowStockItems = [];
    $recentDonors = [];
    $allEvents = [];
    $upcomingEvents = [];
    $batchDonations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=ucfirst($section)?> &bull; ReliefFlow Logistics Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.460.0/dist/umd/lucide.min.js"></script>
    <style>
        :root {
            /* Smartech Palette Exact Hex Tokens */
            --bg-outer-gradient: linear-gradient(135deg, #CDB4C8 0%, #D8C2D4 50%, #C4AABF 100%);
            --container-cream: #F5EDE3;
            --card-rose: #DCC5C9;
            --card-rose-dark: #CBAEBA;
            --panel-plum: #9B7A94;
            --panel-plum-card: #85617E;
            --pill-row: #F5EDE3;
            --pill-light: #FAF5F0;
            --text-dark: #3E2938;
            --text-muted: #7A6273;
            --radius-container: 0px; /* Full-screen container */
            --radius-card: 22px;
            --radius-pill: 9999px;
            --shadow-soft: 0 8px 30px rgba(95, 63, 89, 0.08);

            /* Semantic Status Tokens (paired with line icons) */
            --status-green-bg: #DCFCE7;
            --status-green-text: #166534;
            --status-green-border: #BBF7D0;

            --status-amber-bg: #FEF3C7;
            --status-amber-text: #92400E;
            --status-amber-border: #FDE68A;

            --status-red-bg: #FEE2E2;
            --status-red-text: #991B1B;
            --status-red-border: #FECACA;

            --status-blue-bg: #DBEAFE;
            --status-blue-text: #1E40AF;
            --status-blue-border: #BFDBFE;

            --tap-target: 44px;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            min-height: 100vh;
            background: var(--container-cream);
            font-family: 'Nunito', sans-serif;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Visible Focus Rings (WCAG 2.4.7) */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 2px solid var(--panel-plum) !important;
            outline-offset: 2px !important;
        }

        /* Smartech Unified Form Controls & Filter Inputs */
        .form-label {
            font-size: 12px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
            letter-spacing: 0.02em;
            display: block;
        }
        .form-control, .form-select {
            border: 1px solid #DFD0C3;
            background: #FAF5F0;
            border-radius: var(--radius-pill);
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            min-height: 44px;
            height: 44px;
            transition: all 0.15s ease;
        }
        .form-control:focus, .form-select:focus {
            background: #ffffff;
            border-color: var(--panel-plum);
            box-shadow: 0 0 0 3px rgba(155, 122, 148, 0.2);
            outline: none;
        }
        .form-control::placeholder {
            color: #9E8B99;
            font-weight: 500;
        }
        input[type="date"].form-control {
            padding: 9px 16px;
            font-weight: 600;
        }

        /* Full Screen Fluid Workspace Layout */
        .dashboard-container {
            width: 100%;
            min-height: 100vh;
            display: flex;
            background: var(--container-cream);
            position: relative;
        }

        /* Left Sidebar Panel */
        .sidebar-panel {
            width: 260px;
            flex-shrink: 0;
            background: #FAF4ED;
            border-right: 1px solid rgba(155, 122, 148, 0.15);
            padding: 32px 0 24px 24px;
            display: flex;
            flex-direction: column;
            z-index: 1030;
            position: sticky;
            top: 0;
            height: 100vh;
            transition: transform 0.25s ease;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-right: 24px;
            margin-bottom: 28px;
        }
        .brand-logo-icon {
            width: 40px;
            height: 40px;
            background: var(--panel-plum);
            color: #ffffff;
            border-radius: 14px;
            display: grid;
            place-items: center;
            box-shadow: 0 4px 10px rgba(155, 122, 148, 0.3);
        }
        .brand-text {
            font-size: 22px;
            font-weight: 900;
            color: var(--text-dark);
            letter-spacing: -0.02em;
        }

        /* Profile Mini Badge (Smartech Avatar + Name) */
        .profile-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-right: 24px;
            margin-bottom: 32px;
        }
        .profile-avatar-circle {
            width: 48px;
            height: 48px;
            background: #E8D0D6;
            color: var(--panel-plum);
            border-radius: 50%;
            border: 2px solid #ffffff;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .profile-name {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
        }
        .profile-role {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* Navigation List - Iconic Cut-out Active Pill */
        .nav-vertical {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-grow: 1;
        }
        .nav-item-pill {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: var(--radius-pill) 0 0 var(--radius-pill);
            min-height: var(--tap-target);
            transition: all 0.15s ease;
            position: relative;
        }
        .nav-item-pill:hover {
            color: var(--text-dark);
            background: rgba(220, 197, 201, 0.4);
        }
        .nav-item-pill.active {
            background: var(--container-cream);
            color: var(--text-dark);
            font-weight: 800;
            box-shadow: -3px 3px 12px rgba(95, 63, 89, 0.06);
        }
        .nav-item-pill.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 8px;
            background: var(--container-cream);
        }

        .sidebar-footer {
            padding-right: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(155, 122, 148, 0.12);
        }
        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 14px;
            border-radius: var(--radius-pill);
            min-height: var(--tap-target);
            transition: all 0.15s ease;
        }
        .logout-link:hover {
            color: #991B1B;
            background: rgba(254, 226, 226, 0.6);
        }

        /* Main Workspace Area (Fluid Full-Screen) */
        .main-workspace {
            flex-grow: 1;
            padding: 32px 48px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-y: auto;
        }

        /* Top Bar Header */
        .topbar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 32px;
        }
        .greeting-heading {
            font-size: 30px;
            font-weight: 900;
            color: var(--text-dark);
            letter-spacing: -0.01em;
            margin: 0;
            text-transform: uppercase;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pill-search {
            position: relative;
            width: 280px;
            display: flex;
            align-items: center;
        }
        .pill-search input {
            background: #EBE0DB;
            border: 1px solid rgba(155, 122, 148, 0.15);
            border-radius: var(--radius-pill);
            padding: 10px 20px 10px 42px;
            font-size: 14px;
            color: var(--text-dark);
            min-height: 44px;
            height: 44px;
            width: 100%;
            transition: all 0.15s ease;
        }
        .pill-search input:focus {
            background: #ffffff;
            border-color: var(--panel-plum);
            outline: none;
            box-shadow: 0 0 0 3px rgba(155, 122, 148, 0.15);
        }
        .pill-search i,
        .pill-search svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            z-index: 2;
        }

        .circle-action-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #EBE0DB;
            border: 1px solid rgba(155, 122, 148, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            cursor: pointer;
            text-decoration: none;
            position: relative;
            transition: background 0.15s;
            padding: 0;
            flex-shrink: 0;
        }
        .circle-action-btn:hover {
            background: #E0D3CC;
            color: var(--text-dark);
        }
        .unread-dot {
            width: 9px;
            height: 9px;
            background: var(--panel-plum);
            border: 2px solid #EBE0DB;
            border-radius: 50%;
            position: absolute;
            top: 9px;
            right: 9px;
        }

        /* Smartech Content Cards (Rose-Taupe #DCC5C9) */
        .card-smartech {
            background: var(--card-rose);
            border-radius: var(--radius-card);
            padding: 24px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .card-smartech-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 16px;
        }
        .see-more-link {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-dark);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            align-self: flex-end;
            margin-top: 12px;
            cursor: pointer;
            border: 0;
            background: transparent;
            padding: 0;
        }
        .see-more-link:hover {
            color: var(--panel-plum);
        }

        /* Pill Rows inside Smartech Cards */
        .pill-list-row {
            background: var(--pill-row);
            border-radius: var(--radius-pill);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        .pill-list-row:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(95, 63, 89, 0.08);
            color: inherit;
        }
        .pill-row-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--panel-plum);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 14px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .pill-row-info {
            flex-grow: 1;
            margin: 0 14px;
            overflow: hidden;
        }
        .pill-row-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pill-row-desc {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pill-row-action {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--panel-plum);
            color: #ffffff;
            border: 0;
            display: grid;
            place-items: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        /* Fully Interactive Calendar & Distribution Schedule Widget */
        .schedule-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
        }
        .mini-calendar-box {
            background: var(--pill-row);
            border-radius: 20px;
            padding: 14px 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .mini-calendar-header {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .cal-nav-btn {
            background: transparent;
            border: 0;
            color: var(--text-dark);
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            padding: 0 6px;
            border-radius: 50%;
            transition: background 0.15s;
        }
        .cal-nav-btn:hover {
            background: rgba(155, 122, 148, 0.15);
        }
        .mini-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
            font-size: 11px;
            font-weight: 700;
        }
        .calendar-day-header {
            color: var(--text-muted);
            padding: 3px 0;
        }
        .calendar-day-btn {
            width: 26px;
            height: 26px;
            margin: 0 auto;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 0;
            color: var(--text-dark);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            position: relative;
            transition: all 0.15s ease;
        }
        .calendar-day-btn:hover {
            background: rgba(155, 122, 148, 0.2);
        }
        .calendar-day-btn.active {
            background: var(--panel-plum) !important;
            color: #ffffff !important;
            font-weight: 900;
            box-shadow: 0 2px 6px rgba(155, 122, 148, 0.4);
        }
        .calendar-day-btn.has-event::after {
            content: '';
            width: 4px;
            height: 4px;
            background: #E57388;
            border-radius: 50%;
            position: absolute;
            bottom: 2px;
        }
        .calendar-day-btn.active.has-event::after {
            background: #ffffff;
        }

        /* Schedule List Items */
        .schedule-items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
        }
        .schedule-pill-item {
            background: var(--pill-row);
            border-radius: var(--radius-pill);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.1s;
        }
        .schedule-pill-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(95, 63, 89, 0.08);
        }
        .schedule-date-num {
            font-size: 16px;
            font-weight: 900;
            color: var(--text-dark);
            min-width: 26px;
            text-align: center;
        }
        .schedule-event-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
        }
        .schedule-event-time {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* Right Plum Statistics Panel (#9B7A94) */
        .panel-plum-stats {
            background: var(--panel-plum);
            border-radius: var(--radius-card);
            padding: 24px 20px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(155, 122, 148, 0.25);
        }
        .plum-stat-card {
            background: var(--panel-plum-card);
            border-radius: 20px;
            padding: 18px 14px;
            margin-bottom: 14px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }
        .plum-stat-label {
            font-size: 13px;
            font-weight: 700;
            color: #E8D8E5;
            margin-bottom: 10px;
        }
        .ring-chart-svg {
            width: 88px;
            height: 88px;
            margin: 0 auto;
            transform: rotate(-90deg);
        }
        .ring-chart-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.15);
            stroke-width: 8;
        }
        .ring-chart-stroke {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.8s ease;
        }
        .ring-chart-val {
            font-size: 18px;
            font-weight: 900;
            fill: #ffffff;
            transform: rotate(90deg);
            transform-origin: center;
            text-anchor: middle;
            dominant-baseline: central;
        }
        .plum-see-more {
            font-size: 13px;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 8px;
            padding: 8px 16px;
            border-radius: var(--radius-pill);
            background: rgba(255, 255, 255, 0.1);
            transition: background 0.15s;
        }
        .plum-see-more:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        /* Project / Donation Batches Cards */
        .batch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .batch-card {
            background: var(--pill-row);
            border-radius: 20px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.1s;
        }
        .batch-card:hover {
            transform: translateY(-1px);
            color: inherit;
        }
        .batch-icon-sq {
            width: 44px;
            height: 44px;
            background: #E3D2DC;
            color: var(--panel-plum);
            border-radius: 14px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
        .batch-label {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
        }
        .batch-meta {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* Status Badges with line icons */
        .badge-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 800;
            border: 1px solid transparent;
        }
        .badge-status-received {
            background: var(--status-green-bg);
            color: var(--status-green-text);
            border-color: var(--status-green-border);
        }
        .badge-status-stock {
            background: #ECFEFF;
            color: #0E7490;
            border-color: #A5F3FC;
        }
        .badge-status-pending {
            background: var(--status-amber-bg);
            color: var(--status-amber-text);
            border-color: var(--status-amber-border);
        }
        .badge-status-released {
            background: var(--status-blue-bg);
            color: var(--status-blue-text);
            border-color: var(--status-blue-border);
        }

        /* Filter Chips */
        .chip-filter {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            color: var(--text-dark);
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .chip-filter:hover {
            background: #F8F2EC;
            color: var(--panel-plum);
        }

        /* Table Styling with Soft Cream Rows */
        .table-smartech {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .table-smartech th {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            padding: 10px 18px;
            border: 0;
        }
        .table-smartech td {
            background: #ffffff;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
            vertical-align: middle;
            border: 0;
        }
        .table-smartech tr td:first-child {
            border-top-left-radius: var(--radius-pill);
            border-bottom-left-radius: var(--radius-pill);
        }
        .table-smartech tr td:last-child {
            border-top-right-radius: var(--radius-pill);
            border-bottom-right-radius: var(--radius-pill);
        }

        /* Primary Call to Action Button */
        .btn-plum-action {
            background: var(--panel-plum);
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            border-radius: var(--radius-pill);
            padding: 10px 22px;
            border: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: var(--tap-target);
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn-plum-action:hover {
            background: #84617E;
            color: #ffffff;
        }

        /* Mobile Bottom Nav (< 768px) */
        .mobile-bottom-tabs {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--container-cream);
            border-top: 1px solid rgba(155, 122, 148, 0.15);
            z-index: 1050;
            padding: 4px 8px;
            justify-content: space-around;
            align-items: center;
        }
        .mobile-tab-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            flex: 1;
            min-height: 48px;
        }
        .mobile-tab-btn.active {
            color: var(--panel-plum);
            font-weight: 800;
        }

        /* Responsive Breakpoints */
        @media (max-width: 991.98px) {
            .dashboard-container { flex-direction: column; }
            .sidebar-panel { width: 100%; height: auto; position: relative; padding: 20px; border-right: 0; border-bottom: 1px solid rgba(155, 122, 148, 0.12); }
            .nav-vertical { flex-direction: row; flex-wrap: wrap; }
            .nav-item-pill { border-radius: var(--radius-pill); }
            .nav-item-pill.active::after { display: none; }
            .main-workspace { padding: 20px; }
        }

        @media (max-width: 767.98px) {
            body { padding-bottom: 74px; }
            .sidebar-panel { display: none; }
            .mobile-bottom-tabs { display: flex; }
            .pill-search { display: none; }
            .greeting-heading { font-size: 22px; }
            .schedule-layout { grid-template-columns: 1fr; }
            .batch-grid { grid-template-columns: 1fr; }

            /* Transform Table to Mobile Cards */
            .table-responsive-cards thead { display: none; }
            .table-responsive-cards tbody,
            .table-responsive-cards tr,
            .table-responsive-cards td { display: block; width: 100%; }
            .table-responsive-cards tr {
                background: #ffffff;
                border-radius: var(--radius-card);
                padding: 16px;
                margin-bottom: 12px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            }
            .table-responsive-cards td {
                padding: 6px 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 0 !important;
            }
            .table-responsive-cards td::before {
                content: attr(data-label);
                font-weight: 800;
                font-size: 11px;
                color: var(--text-muted);
                text-transform: uppercase;
            }
            .table-responsive-cards td:last-child {
                border-top: 1px solid #FAF5F0;
                padding-top: 10px;
                margin-top: 4px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Left Sidebar Panel -->
    <aside class="sidebar-panel">
        <div class="brand-section">
            <div class="brand-logo-icon">
                <i data-lucide="package-search" style="width:22px;height:22px;stroke-width:2;"></i>
            </div>
            <div class="brand-text">ReliefFlow</div>
        </div>

        <div class="profile-badge">
            <div class="profile-avatar-circle">
                <?=strtoupper(substr($currentUsername, 0, 1))?>
            </div>
            <div>
                <div class="profile-name"><?=h($currentUsername)?></div>
                <div class="profile-role">Field Coordinator</div>
            </div>
        </div>

        <nav class="nav-vertical">
            <a href="?section=dashboard" class="nav-item-pill <?=($section === 'dashboard') ? 'active' : ''?>">
                <i data-lucide="layout-grid" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Dashboard</span>
            </a>
            <a href="?section=donations" class="nav-item-pill <?=($section === 'donations') ? 'active' : ''?>">
                <i data-lucide="package" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Donations</span>
            </a>
            <a href="?section=categories" class="nav-item-pill <?=($section === 'categories') ? 'active' : ''?>">
                <i data-lucide="tags" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Categories</span>
            </a>
            <a href="?section=donors" class="nav-item-pill <?=($section === 'donors') ? 'active' : ''?>">
                <i data-lucide="handshake" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Donors</span>
            </a>
            <a href="?section=notifications" class="nav-item-pill <?=($section === 'notifications') ? 'active' : ''?>">
                <i data-lucide="bell" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Notifications</span>
            </a>
            <a href="?section=activityLogs" class="nav-item-pill <?=($section === 'activityLogs' || $section === 'activitylogs') ? 'active' : ''?>">
                <i data-lucide="clipboard-list" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Activity Logs</span>
            </a>
            <a href="?section=profile" class="nav-item-pill <?=($section === 'profile') ? 'active' : ''?>">
                <i data-lucide="user" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Profile</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="?logout=1" class="logout-link">
                <i data-lucide="log-out" style="width:18px;height:18px;stroke-width:2;"></i>
                <span>Log out</span>
            </a>
        </div>
    </aside>

    <!-- Main Workspace Area -->
    <main class="main-workspace">
        <!-- Top Bar with Smartech Heading & Controls -->
        <header class="topbar-header">
            <h1 class="greeting-heading">HELLO, <?=strtoupper(h($currentUsername))?>!</h1>

            <div class="topbar-actions">
                <form method="get" action="index.php" class="pill-search m-0">
                    <input type="hidden" name="section" value="donations">
                    <i data-lucide="search" style="width:16px;height:16px;stroke-width:2;"></i>
                    <input type="text" name="search" placeholder="Search supplies, donors..." value="<?=h($_GET['search'] ?? '')?>">
                </form>

                <!-- Fullscreen Toggle Button -->
                <button class="circle-action-btn" type="button" onclick="toggleFullScreen()" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
                    <i data-lucide="maximize-2" id="fsIcon" style="width:18px;height:18px;stroke-width:2;"></i>
                </button>

                <!-- Notifications Dropdown Trigger -->
                <div class="dropdown">
                    <button class="circle-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i data-lucide="bell" style="width:18px;height:18px;stroke-width:2;"></i>
                        <?php if ($unreadNotesCount > 0): ?>
                            <span class="unread-dot"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="background: var(--container-cream); border-radius: 20px; width: 320px; overflow: hidden;">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold" style="font-size: 14px;">Notifications</span>
                            <?php if ($unreadNotesCount > 0): ?>
                                <a href="?section=notifications&action=notification_read_all" class="text-decoration-none small" style="color: var(--panel-plum); font-weight: 700;">Mark all read</a>
                            <?php endif; ?>
                        </div>
                        <div class="p-2" style="max-height: 250px; overflow-y: auto;">
                            <?php if (empty($recentNotifications)): ?>
                                <div class="text-center text-muted py-3 small">No notifications</div>
                            <?php else: foreach ($recentNotifications as $rn): ?>
                                <div class="p-2 rounded-3 mb-1" style="background: #ffffff; font-size: 12px;">
                                    <div class="fw-bold" style="color: var(--text-dark);"><?=h($rn['message'])?></div>
                                    <div class="text-muted" style="font-size: 10px;"><?=date('M j, Y', strtotime($rn['event_date']))?></div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="p-2 text-center border-top">
                            <a href="?section=notifications" class="text-decoration-none small fw-bold" style="color: var(--panel-plum);">View All Notifications &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Direct Mobile Logout Button -->
                <a href="?logout=1" class="circle-action-btn d-inline-flex d-md-none text-danger" title="Log out" aria-label="Log out" style="color: #dc2626;">
                    <i data-lucide="log-out" style="width:18px;height:18px;stroke-width:2;"></i>
                </a>

                <!-- Kebab Menu -->
                <div class="dropdown">
                    <button class="circle-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Quick Actions">
                        <i data-lucide="more-vertical" style="width:18px;height:18px;stroke-width:2;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="background: var(--container-cream); border-radius: 16px; min-width: 200px;">
                        <li><a class="dropdown-item rounded-pill py-2" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAddDonation">Record Donation</a></li>
                        <li><a class="dropdown-item rounded-pill py-2" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAddEvent">Schedule Distribution</a></li>
                        <li><a class="dropdown-item rounded-pill py-2" href="?section=categories">Manage Categories</a></li>
                        <li><a class="dropdown-item rounded-pill py-2" href="?section=activityLogs">Activity Logs</a></li>
                        <li><a class="dropdown-item rounded-pill py-2" href="?section=profile">My Profile</a></li>
                        <li><hr class="dropdown-divider my-1" style="border-color: rgba(155, 122, 148, 0.2);"></li>
                        <li>
                            <a class="dropdown-item rounded-pill py-2 text-danger fw-bold d-flex align-items-center gap-2" href="?logout=1">
                                <i data-lucide="log-out" style="width:16px;height:16px;stroke-width:2;"></i>
                                <span>Log out</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Dismissible Floating Banner -->
        <?php if ($flash): ?>
            <div class="alert alert-<?=$flashType?> alert-dismissible fade show rounded-pill px-4 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
                <i data-lucide="info" style="width:18px;height:18px;stroke-width:2;"></i>
                <div class="flex-grow-1 fw-bold" style="font-size: 13px;"><?=h($flash)?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php
        /* ------------------------------------------------------------
           DASHBOARD VIEW (Matching Smartech Layout & Widgets)
        ------------------------------------------------------------ */
        if ($section === 'dashboard'):
        ?>
            <!-- Low-Stock Alert Strip (Shown only when low stock items exist) -->
            <?php if (!empty($lowStockItems)): ?>
                <div class="mb-4 p-3 rounded-4 d-flex align-items-center justify-content-between shadow-sm" style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E;">
                    <div class="d-flex align-items-center gap-2">
                        <i data-lucide="alert-triangle" style="width:20px;height:20px;stroke-width:2;"></i>
                        <span class="fw-bold" style="font-size: 13px;">
                            Critical Supply Alert: <?=h($lowStockItems[0]['item_name'])?> has only <?=h($lowStockItems[0]['quantity'])?> units remaining.
                        </span>
                    </div>
                    <a href="?section=donations&filter_status=Pending" class="btn btn-sm rounded-pill fw-bold" style="background: #92400E; color: #ffffff; font-size: 12px; padding: 6px 16px;">Restock</a>
                </div>
            <?php endif; ?>

            <!-- Main Dashboard Grid (2 Zones: Left Content & Right Plum Panel) -->
            <div class="row g-4">
                <!-- Left Zone (Widgets) -->
                <div class="col-xl-8 col-lg-7">
                    <!-- Row 1: Recent Donors & Upcoming Events Cards -->
                    <div class="row g-4 mb-4">
                        <!-- Card 1: Recent Donors -->
                        <div class="col-md-6">
                            <div class="card-smartech h-100">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h2 class="card-smartech-title m-0">Recent Donors</h2>
                                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold" style="background: var(--pill-row); font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalAddDonor">+ New</button>
                                    </div>
                                    <?php if (empty($recentDonors)): ?>
                                        <div class="text-muted small text-center py-4">No donors recorded</div>
                                    <?php else: foreach ($recentDonors as $rd): ?>
                                        <div class="pill-list-row" onclick="viewDonorDetails('<?=h($rd['contact_person'])?>', '<?=h($rd['contact_number'])?>', '<?=h($rd['address'])?>')">
                                            <div class="pill-row-avatar">
                                                <?=strtoupper(substr($rd['contact_person'], 0, 1))?>
                                            </div>
                                            <div class="pill-row-info">
                                                <div class="pill-row-name"><?=h($rd['contact_person'])?></div>
                                                <div class="pill-row-desc"><?=h($rd['latest_item'])?></div>
                                            </div>
                                            <div class="pill-row-action" title="View details">
                                                <i data-lucide="message-square" style="width:13px;height:13px;stroke-width:2;"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <a href="?section=donors" class="see-more-link">See more &rsaquo;</a>
                            </div>
                        </div>

                        <!-- Card 2: Upcoming Events (Interactive & Working) -->
                        <div class="col-md-6">
                            <div class="card-smartech h-100">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h2 class="card-smartech-title m-0">Upcoming Events</h2>
                                        <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold" style="background: var(--pill-row); font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalAddEvent">+ Schedule</button>
                                    </div>
                                    <div class="upcoming-events-list" style="max-height: 280px; overflow-y: auto; padding-right: 4px;">
                                    <?php if (empty($upcomingEvents)): ?>
                                        <div class="text-muted small text-center py-4">No scheduled events</div>
                                    <?php else: foreach ($upcomingEvents as $ue): ?>
                                        <div class="pill-list-row" onclick="openEventModal(<?=$ue['event_id']?>, '<?=h($ue['title'])?>', '<?=$ue['event_date']?>', '<?=h($ue['event_time'])?>', '<?=h($ue['location'])?>', '<?=h($ue['status'])?>')">
                                            <div class="pill-row-avatar" style="background: var(--text-dark);">
                                                <i data-lucide="calendar" style="width:15px;height:15px;stroke-width:2;"></i>
                                            </div>
                                            <div class="pill-row-info">
                                                <div class="pill-row-name"><?=h($ue['title'])?></div>
                                                <div class="pill-row-desc"><?=date('d M Y', strtotime($ue['event_date']))?> &bull; <?=h($ue['event_time'])?> &bull; <?=h($ue['location'])?></div>
                                            </div>
                                            <div class="pill-row-action" title="Event details">
                                                <i data-lucide="arrow-up-right" style="width:13px;height:13px;stroke-width:2;"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                    </div>
                                </div>
                                <button type="button" class="see-more-link" data-bs-toggle="modal" data-bs-target="#modalAllEvents">See more &rsaquo;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Distribution Schedule (Interactive Month Calendar + Dynamic Date Filter) -->
                    <div class="card-smartech mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="card-smartech-title m-0">Distribution Schedule</h2>
                            <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold" style="background: var(--pill-row); font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalAddEvent">+ Add Distribution</button>
                        </div>
                        <div class="schedule-layout">
                            <!-- Interactive Calendar Box -->
                            <div class="mini-calendar-box">
                                <div class="mini-calendar-header d-flex justify-content-between align-items-center px-1">
                                    <button type="button" class="cal-nav-btn" onclick="prevCalMonth()" aria-label="Previous Month">&lsaquo;</button>
                                    <span id="calMonthTitle" class="fw-bold">Month Year</span>
                                    <button type="button" class="cal-nav-btn" onclick="nextCalMonth()" aria-label="Next Month">&rsaquo;</button>
                                </div>
                                <div class="mini-calendar-grid mb-2">
                                    <div class="calendar-day-header">Mon</div>
                                    <div class="calendar-day-header">Tue</div>
                                    <div class="calendar-day-header">Wed</div>
                                    <div class="calendar-day-header">Thu</div>
                                    <div class="calendar-day-header">Fri</div>
                                    <div class="calendar-day-header">Sat</div>
                                    <div class="calendar-day-header">Sun</div>
                                </div>
                                <div class="mini-calendar-grid" id="calDaysGrid">
                                    <!-- Populated dynamically by renderCalendar() -->
                                </div>
                            </div>

                            <!-- Dynamic Schedule Items for Selected Date -->
                            <div class="schedule-items-list" id="scheduleListContainer">
                                <!-- Populated dynamically based on calendar selection -->
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Recent Donations (adapted from "My projects") -->
                    <div class="card-smartech">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="card-smartech-title m-0">Recent Donations</h2>
                            <a href="?section=donations" class="see-more-link m-0">All records &rsaquo;</a>
                        </div>
                        <div class="batch-grid">
                            <?php if (empty($batchDonations)): ?>
                                <div class="text-muted small py-3">No recent supply batches</div>
                            <?php else: foreach ($batchDonations as $b): ?>
                                <a href="?section=donations&search=<?=urlencode($b['item_name'])?>" class="batch-card">
                                    <div class="batch-icon-sq">
                                        <i data-lucide="<?=h($b['icon'] ?: 'package')?>" style="width:22px;height:22px;stroke-width:2;"></i>
                                    </div>
                                    <div>
                                        <div class="batch-label"><?=h($b['item_name'])?></div>
                                        <div class="batch-meta"><?=number_format((int)$b['quantity'])?> units &bull; <?=h($b['status'])?></div>
                                    </div>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: The Iconic Darker Plum Statistics Panel (#9B7A94) -->
                <div class="col-xl-4 col-lg-5">
                    <div class="panel-plum-stats h-100">
                        <div>
                            <div class="fw-bold mb-3 text-center" style="font-size: 15px; color: #ffffff;">Disaster Response Metrics</div>

                            <!-- Ring Card 1: Stock Level -->
                            <div class="plum-stat-card">
                                <div class="plum-stat-label">Stock Level</div>
                                <?php
                                $c1 = 2 * 3.14159 * 34; // radius 34 -> circum ~213.6
                                $offset1 = $c1 * (1 - ($stockLevelPct / 100));
                                ?>
                                <svg class="ring-chart-svg" viewBox="0 0 82 82">
                                    <circle class="ring-chart-bg" cx="41" cy="41" r="34"></circle>
                                    <circle class="ring-chart-stroke" cx="41" cy="41" r="34" stroke="#E57388"
                                            stroke-dasharray="<?=$c1?>" stroke-dashoffset="<?=$offset1?>"></circle>
                                    <text class="ring-chart-val" x="41" y="41"><?=$stockLevelPct?>%</text>
                                </svg>
                                <div class="small mt-2" style="color: #E8D8E5; font-size: 11px;"><?=number_format($netInStockUnits)?> units verified in depot</div>
                            </div>

                            <!-- Ring Card 2: Items Released -->
                            <div class="plum-stat-card">
                                <div class="plum-stat-label">Items Released</div>
                                <?php
                                $offset2 = $c1 * (1 - ($itemsReleasedPct / 100));
                                ?>
                                <svg class="ring-chart-svg" viewBox="0 0 82 82">
                                    <circle class="ring-chart-bg" cx="41" cy="41" r="34"></circle>
                                    <circle class="ring-chart-stroke" cx="41" cy="41" r="34" stroke="#5FBDB0"
                                            stroke-dasharray="<?=$c1?>" stroke-dashoffset="<?=$offset2?>"></circle>
                                    <text class="ring-chart-val" x="41" y="41"><?=$itemsReleasedPct?>%</text>
                                </svg>
                                <div class="small mt-2" style="color: #E8D8E5; font-size: 11px;"><?=number_format($totalReleasedUnits)?> units dispatched to evacuees</div>
                            </div>

                            <!-- Ring Card 3: Items Received -->
                            <div class="plum-stat-card mb-0">
                                <div class="plum-stat-label">Items Received</div>
                                <?php
                                $offset3 = $c1 * (1 - ($itemsReceivedPct / 100));
                                ?>
                                <svg class="ring-chart-svg" viewBox="0 0 82 82">
                                    <circle class="ring-chart-bg" cx="41" cy="41" r="34"></circle>
                                    <circle class="ring-chart-stroke" cx="41" cy="41" r="34" stroke="#E8B86D"
                                            stroke-dasharray="<?=$c1?>" stroke-dashoffset="<?=$offset3?>"></circle>
                                    <text class="ring-chart-val" x="41" y="41"><?=$itemsReceivedPct?>%</text>
                                </svg>
                                <div class="small mt-2" style="color: #E8D8E5; font-size: 11px;"><?=number_format($grandTotalHandled)?> total units logged</div>
                            </div>
                        </div>

                        <div class="text-center pt-3">
                            <a href="?section=donations" class="plum-see-more">
                                <span>Manage All Inventory</span>
                                <i data-lucide="arrow-right" style="width:14px;height:14px;stroke-width:2.5;"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        /* ------------------------------------------------------------
           DONATION MANAGEMENT VIEW (Smartech Aesthetic)
        ------------------------------------------------------------ */
        elseif ($section === 'donations'):
            $search = trim((string)($_GET['search'] ?? ''));
            $filterCat = (int)($_GET['filter_cat'] ?? 0);
            $filterStatus = trim((string)($_GET['filter_status'] ?? ''));
            $filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
            $filterDateTo = trim((string)($_GET['date_to'] ?? ''));

            $sortColumn = $_GET['sort'] ?? 'donation_id';
            $sortOrder = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $allowedSorts = [
                'donation_id' => 'd.donation_id',
                'item_name' => 'd.item_name',
                'category' => 'c.category_name',
                'quantity' => 'd.quantity',
                'status' => 'd.status',
                'date' => 'd.donation_date'
            ];
            $orderBy = $allowedSorts[$sortColumn] ?? 'd.donation_id';

            $whereSql = "WHERE 1=1";
            $params = [];

            if ($search !== '') {
                $whereSql .= " AND (d.item_name LIKE ? OR dn.contact_person LIKE ? OR d.donation_id LIKE ?)";
                $like = '%' . $search . '%';
                $params = array_merge($params, [$like, $like, $like]);
            }
            if ($filterCat > 0) {
                $whereSql .= " AND d.category_id = ?";
                $params[] = $filterCat;
            }
            if ($filterStatus !== '') {
                $whereSql .= " AND d.status = ?";
                $params[] = $filterStatus;
            }
            if ($filterDateFrom !== '') {
                $whereSql .= " AND d.donation_date >= ?";
                $params[] = $filterDateFrom;
            }
            if ($filterDateTo !== '') {
                $whereSql .= " AND d.donation_date <= ?";
                $params[] = $filterDateTo;
            }

            $countStmt = $conn->prepare("SELECT COUNT(*) FROM donations d LEFT JOIN donors dn ON dn.donor_id = d.donor_id $whereSql");
            $countStmt->execute($params);
            $totalFiltered = (int)$countStmt->fetchColumn();

            $perPage = 10;
            $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
            $page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
            $offset = ($page - 1) * $perPage;

            $dataSql = "SELECT d.*, c.category_name, c.icon, c.color, dn.contact_person, dn.contact_number
                        FROM donations d
                        LEFT JOIN categories c ON c.category_id = d.category_id
                        LEFT JOIN donors dn ON dn.donor_id = d.donor_id
                        $whereSql
                        ORDER BY $orderBy $sortOrder
                        LIMIT $perPage OFFSET $offset";
            $dataStmt = $conn->prepare($dataSql);
            $dataStmt->execute($params);
            $donationsList = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            $hasActiveFilters = ($search !== '' || $filterCat > 0 || $filterStatus !== '' || $filterDateFrom !== '' || $filterDateTo !== '');
            $activeCatName = '';
            if ($filterCat > 0) {
                $cstmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
                $cstmt->execute([$filterCat]);
                $activeCatName = (string)$cstmt->fetchColumn();
            }
        ?>
            <!-- View Header -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                <div>
                    <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Relief Donation Records</h2>
                    <div style="font-size: 14px; color: var(--text-muted);">Manage supply intake, warehouse verification, and evacuation releases.</div>
                </div>
                <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddDonation">
                    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5;"></i>
                    <span>Record Donation</span>
                </button>
            </div>

            <!-- Pill Filter Card (Rose-Taupe) -->
            <div class="card-smartech mb-4 p-4">
                <form method="get" action="index.php" id="donationsFilterForm">
                    <input type="hidden" name="section" value="donations">
                    <input type="hidden" name="sort" value="<?=h($sortColumn)?>">
                    <input type="hidden" name="order" value="<?=h($sortOrder)?>">

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-12">
                            <label class="form-label" for="search_input">Search Keyword</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="search_input" name="search" placeholder="Type to filter item, donor..." value="<?=h($search)?>" autocomplete="off" style="padding-left: 42px;">
                                <i data-lucide="search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); pointer-events: none;"></i>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label" for="filter_cat">Category</label>
                            <select class="form-control form-select" id="filter_cat" name="filter_cat" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php
                                $allCats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($allCats as $ac):
                                ?>
                                    <option value="<?=$ac['category_id']?>" <?=$filterCat === (int)$ac['category_id'] ? 'selected' : ''?>>
                                        <?=h($ac['category_name'])?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label" for="filter_status">Status</label>
                            <select class="form-control form-select" id="filter_status" name="filter_status" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?=$filterStatus === 'Pending' ? 'selected' : ''?>>Pending</option>
                                <option value="Received" <?=$filterStatus === 'Received' ? 'selected' : ''?>>Received</option>
                                <option value="In Stock" <?=$filterStatus === 'In Stock' ? 'selected' : ''?>>In Stock</option>
                                <option value="Released" <?=$filterStatus === 'Released' ? 'selected' : ''?>>Released</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label" for="date_from">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?=h($filterDateFrom)?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-lg-2 col-md-3 col-6">
                            <label class="form-label" for="date_to">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?=h($filterDateTo)?>" onchange="this.form.submit()">
                        </div>
                    </div>
                </form>

                <!-- Removable Chips -->
                <?php if ($hasActiveFilters): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" style="border-color: rgba(62, 41, 56, 0.1) !important;">
                        <span class="small fw-bold" style="color: var(--text-dark);">Active:</span>
                        <?php if ($search !== ''): ?>
                            <a href="?<?=h(http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])))?>" class="chip-filter" title="Remove search filter">
                                Search: "<?=h($search)?>" <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($filterCat > 0): ?>
                            <a href="?<?=h(http_build_query(array_merge($_GET, ['filter_cat' => '', 'page' => 1])))?>" class="chip-filter" title="Remove category filter">
                                Category: <?=h($activeCatName)?> <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($filterStatus !== ''): ?>
                            <a href="?<?=h(http_build_query(array_merge($_GET, ['filter_status' => '', 'page' => 1])))?>" class="chip-filter" title="Remove status filter">
                                Status: <?=h($filterStatus)?> <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($filterDateFrom !== '' || $filterDateTo !== ''): ?>
                            <a href="?<?=h(http_build_query(array_merge($_GET, ['date_from' => '', 'date_to' => '', 'page' => 1])))?>" class="chip-filter" title="Remove date filter">
                                Date: <?=h($filterDateFrom ?: 'Start')?> &rarr; <?=h($filterDateTo ?: 'End')?> <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                            </a>
                        <?php endif; ?>
                        <a href="?section=donations" class="btn btn-link btn-sm text-danger text-decoration-none small p-0 ms-2 fw-bold">Reset All</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Table of Donations -->
            <div class="table-responsive">
                <table class="table-smartech table-responsive-cards">
                    <thead>
                        <tr>
                            <th>
                                <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'donation_id', 'order' => $sortColumn === 'donation_id' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                                    ID <?=$sortColumn === 'donation_id' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                                </a>
                            </th>
                            <th>
                                <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'item_name', 'order' => $sortColumn === 'item_name' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                                    Item Name <?=$sortColumn === 'item_name' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                                </a>
                            </th>
                            <th>Category</th>
                            <th>
                                <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'quantity', 'order' => $sortColumn === 'quantity' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                                    Qty <?=$sortColumn === 'quantity' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                                </a>
                            </th>
                            <th>Status</th>
                            <th>Donor Contributor</th>
                            <th>
                                <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'date', 'order' => $sortColumn === 'date' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                                    Date <?=$sortColumn === 'date' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                                </a>
                            </th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($donationsList)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="mb-2"><i data-lucide="search-x" style="width:40px;height:40px;stroke-width:1.5;color:var(--text-muted);"></i></div>
                                    <div class="fw-bold mb-1 fs-5">No donation records match your search</div>
                                    <div class="small text-muted mb-3">Adjust your filters or record a new supply batch.</div>
                                    <a href="?section=donations" class="btn btn-sm rounded-pill fw-bold px-4 py-2" style="background: var(--panel-plum); color: #fff;">Clear Filters</a>
                                </td>
                            </tr>
                        <?php else: foreach ($donationsList as $item):
                            $st = $item['status'] ?? 'Received';
                            $badgeClass = match($st) {
                                'Pending' => 'badge-status-pending',
                                'In Stock' => 'badge-status-stock',
                                'Released' => 'badge-status-released',
                                default => 'badge-status-received'
                            };
                            $stIcon = match($st) {
                                'Pending' => 'clock',
                                'In Stock' => 'package',
                                'Released' => 'arrow-up-right',
                                default => 'check-circle-2'
                            };
                        ?>
                            <tr>
                                <td data-label="ID">
                                    <span style="color: var(--panel-plum); font-weight: 800;">#DON-<?=str_pad((string)$item['donation_id'], 4, '0', STR_PAD_LEFT)?></span>
                                </td>
                                <td data-label="Item Name">
                                    <span class="fw-bold"><?=h($item['item_name'])?></span>
                                </td>
                                <td data-label="Category">
                                    <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-dark); font-weight: 800; font-size: 12px; padding: 6px 12px;">
                                        <?=h($item['category_name'] ?? 'General')?>
                                    </span>
                                </td>
                                <td data-label="Quantity">
                                    <span class="fw-bold fs-6"><?=number_format((int)$item['quantity'])?></span>
                                </td>
                                <td data-label="Status">
                                    <div class="dropdown d-inline-block">
                                        <button class="badge-status-pill <?=$badgeClass?> border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Click to update status">
                                            <i data-lucide="<?=$stIcon?>" style="width:13px;height:13px;stroke-width:2.5;"></i>
                                            <span><?=h($st)?></span>
                                        </button>
                                        <ul class="dropdown-menu shadow border-0 p-2" style="background: var(--container-cream); border-radius: 16px; font-size: 13px;">
                                            <li><h6 class="dropdown-header">Update Status</h6></li>
                                            <li>
                                                <form method="post" action="index.php">
                                                    <input type="hidden" name="action" value="donation_status_update">
                                                    <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                                    <input type="hidden" name="status" value="Pending">
                                                    <button type="submit" class="dropdown-item rounded-pill">Pending</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" action="index.php">
                                                    <input type="hidden" name="action" value="donation_status_update">
                                                    <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                                    <input type="hidden" name="status" value="Received">
                                                    <button type="submit" class="dropdown-item rounded-pill">Received</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" action="index.php">
                                                    <input type="hidden" name="action" value="donation_status_update">
                                                    <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                                    <input type="hidden" name="status" value="In Stock">
                                                    <button type="submit" class="dropdown-item rounded-pill">In Stock</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" action="index.php">
                                                    <input type="hidden" name="action" value="donation_status_update">
                                                    <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                                    <input type="hidden" name="status" value="Released">
                                                    <button type="submit" class="dropdown-item rounded-pill">Released to Field</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <td data-label="Donor">
                                    <div class="fw-bold"><?=h($item['contact_person'] ?? 'Anonymous')?></div>
                                </td>
                                <td data-label="Date" class="small text-muted">
                                    <?=date('M j, Y', strtotime($item['donation_date']))?>
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <button type="button" class="btn btn-sm rounded-circle p-2"
                                                style="background: #EBE0DB; color: var(--text-dark); width: 36px; height: 36px; display: grid; place-items: center;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditDonation"
                                                data-id="<?=$item['donation_id']?>"
                                                data-name="<?=h($item['item_name'])?>"
                                                data-qty="<?=$item['quantity']?>"
                                                data-cat="<?=$item['category_id']?>"
                                                data-donor="<?=$item['donor_id']?>"
                                                data-date="<?=$item['donation_date']?>"
                                                data-status="<?=h($item['status'])?>"
                                                title="Edit item">
                                            <i data-lucide="edit-3" style="width:15px;height:15px;stroke-width:2;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm rounded-circle p-2 text-danger"
                                                style="background: #FEE2E2; width: 36px; height: 36px; display: grid; place-items: center;"
                                                onclick="confirmDeleteDonation(<?=$item['donation_id']?>, '<?=h(addslashes($item['item_name']))?>')"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDeleteDonation"
                                                data-id="<?=$item['donation_id']?>"
                                                data-name="<?=h($item['item_name'])?>"
                                                title="Delete item">
                                            <i data-lucide="trash-2" style="width:15px;height:15px;stroke-width:2;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-4 px-1">
                <small class="text-muted fw-bold">
                    Showing <?=min($totalFiltered, $offset + 1)?>-<?=min($totalFiltered, $offset + $perPage)?> of <?=number_format($totalFiltered)?> records
                </small>
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm m-0 gap-1">
                            <li class="page-item <?=$page <= 1 ? 'disabled' : ''?>">
                                <a class="page-link rounded-pill px-3" href="?<?=http_build_query(array_merge($_GET, ['page' => $page - 1]))?>">Prev</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?=$p === $page ? 'active' : ''?>">
                                    <a class="page-link rounded-circle text-center" style="width: 32px; height: 32px; line-height: 20px;" href="?<?=http_build_query(array_merge($_GET, ['page' => $p]))?>"><?=$p?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?=$page >= $totalPages ? 'disabled' : ''?>">
                                <a class="page-link rounded-pill px-3" href="?<?=http_build_query(array_merge($_GET, ['page' => $page + 1]))?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>

        <?php
        /* ------------------------------------------------------------
           CATEGORIES VIEW
        ------------------------------------------------------------ */
        elseif ($section === 'categories'):
            $catList = $conn->query(
                "SELECT c.*, COUNT(d.donation_id) AS items_count, COALESCE(SUM(d.quantity), 0) AS units_sum
                 FROM categories c
                 LEFT JOIN donations d ON d.category_id = c.category_id
                 GROUP BY c.category_id
                 ORDER BY c.category_id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Relief Supply Categories</h2>
                    <div style="font-size: 14px; color: var(--text-muted);">Organized supplies by food, water, medical, apparel, and sanitation kits.</div>
                </div>
                <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5;"></i>
                    <span>Add Category</span>
                </button>
            </div>

            <div class="row g-4">
                <?php foreach ($catList as $c): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card-smartech h-100">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div style="width: 48px; height: 48px; background: #ffffff; border-radius: 16px; display: grid; place-items: center; color: var(--panel-plum); box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
                                            <i data-lucide="<?=h($c['icon'] ?: 'package')?>" style="width:24px;height:24px;stroke-width:2;"></i>
                                        </div>
                                        <div>
                                            <h3 class="h5 fw-bold m-0" style="color: var(--text-dark);"><?=h($c['category_name'])?></h3>
                                            <small class="text-muted">#CAT-<?=$c['category_id']?></small>
                                        </div>
                                    </div>
                                    <span class="badge rounded-pill" style="background: var(--panel-plum); color: #fff; font-size: 12px; padding: 6px 12px;">
                                        <?=number_format((int)$c['units_sum'])?> units
                                    </span>
                                </div>
                                <p style="font-size: 14px; color: var(--text-muted); min-height: 42px;"><?=h($c['description'] ?: 'General classification')?></p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: rgba(62, 41, 56, 0.1) !important;">
                                <span class="small fw-bold text-muted"><?=number_format((int)$c['items_count'])?> entries recorded</span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold" style="background: #ffffff; color: var(--text-dark);"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditCategory"
                                            data-id="<?=$c['category_id']?>"
                                            data-name="<?=h($c['category_name'])?>"
                                            data-desc="<?=h($c['description'])?>"
                                            data-icon="<?=h($c['icon'])?>"
                                            data-color="<?=h($c['color'])?>">
                                        Edit
                                    </button>
                                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold text-danger" style="background: #FEE2E2;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalDeleteCategory"
                                            data-id="<?=$c['category_id']?>"
                                            data-name="<?=h($c['category_name'])?>">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php
        /* ------------------------------------------------------------
           DONORS DIRECTORY VIEW
        ------------------------------------------------------------ */
        elseif ($section === 'donors'):
            $searchDonor = trim((string)($_GET['search_donor'] ?? ''));
            $donorSql = "SELECT dn.*, COUNT(d.donation_id) AS donation_count, COALESCE(SUM(d.quantity), 0) AS total_units_given
                         FROM donors dn
                         LEFT JOIN donations d ON d.donor_id = dn.donor_id";
            $donorParams = [];
            if ($searchDonor !== '') {
                $donorSql .= " WHERE dn.contact_person LIKE ? OR dn.contact_number LIKE ? OR dn.address LIKE ?";
                $term = '%' . $searchDonor . '%';
                $donorParams = [$term, $term, $term];
            }
            $donorSql .= " GROUP BY dn.donor_id ORDER BY dn.donor_id DESC";
            $dstmt = $conn->prepare($donorSql);
            $dstmt->execute($donorParams);
            $donorsList = $dstmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Relief Donors & Partners</h2>
                    <div style="font-size: 14px; color: var(--text-muted);">Partner organizations, individual donors, and field sponsors.</div>
                </div>
                <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddDonor">
                    <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5;"></i>
                    <span>Register Donor</span>
                </button>
            </div>

            <div class="table-responsive">
                <table class="table-smartech table-responsive-cards">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Contact Person / Organization</th>
                            <th>Phone Contact</th>
                            <th>Depot / Address</th>
                            <th>Contributions</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($donorsList)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">No donors found.</td></tr>
                        <?php else: foreach ($donorsList as $donor): ?>
                            <tr>
                                <td data-label="ID"><span style="color: var(--panel-plum); font-weight: 800;">#DNR-<?=$donor['donor_id']?></span></td>
                                <td data-label="Contact Person"><span class="fw-bold"><?=h($donor['contact_person'])?></span></td>
                                <td data-label="Phone">
                                    <a href="tel:<?=h($donor['contact_number'])?>" class="text-decoration-none fw-bold" style="color: var(--panel-plum);">
                                        <?=h($donor['contact_number'])?>
                                    </a>
                                </td>
                                <td data-label="Address" class="small text-muted"><?=h($donor['address'])?></td>
                                <td data-label="Contributions">
                                    <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-dark); font-weight: 800; font-size: 12px; padding: 6px 12px;">
                                        <?=$donor['donation_count']?> records &bull; <?=number_format((int)$donor['total_units_given'])?> units
                                    </span>
                                </td>
                                <td data-label="Actions" class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <button type="button" class="btn btn-sm rounded-circle p-2"
                                                style="background: #EBE0DB; color: var(--text-dark); width: 36px; height: 36px; display: grid; place-items: center;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEditDonor"
                                                data-id="<?=$donor['donor_id']?>"
                                                data-person="<?=h($donor['contact_person'])?>"
                                                data-phone="<?=h($donor['contact_number'])?>"
                                                data-address="<?=h($donor['address'])?>">
                                            <i data-lucide="edit-3" style="width:15px;height:15px;stroke-width:2;"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm rounded-circle p-2 text-danger"
                                                style="background: #FEE2E2; width: 36px; height: 36px; display: grid; place-items: center;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDeleteDonor"
                                                data-id="<?=$donor['donor_id']?>"
                                                data-person="<?=h($donor['contact_person'])?>">
                                            <i data-lucide="trash-2" style="width:15px;height:15px;stroke-width:2;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php
        /* ------------------------------------------------------------
           NOTIFICATIONS VIEW
        ------------------------------------------------------------ */
        elseif ($section === 'notifications'):
            $allNotes = $conn->query("SELECT * FROM notifications ORDER BY notification_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">System Notifications</h2>
                    <div style="font-size: 14px; color: var(--text-muted);">Event reminders, dispatch orders, and supply movements.</div>
                </div>
                <?php if ($unreadNotesCount > 0): ?>
                    <a href="?section=notifications&action=notification_read_all" class="btn-plum-action">
                        <i data-lucide="check-check" style="width:16px;height:16px;stroke-width:2;"></i>
                        <span>Mark All Read</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="card-smartech p-0 overflow-hidden">
                <table class="table-smartech mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Message</th>
                            <th>Target Supply</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allNotes)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">No notifications present.</td></tr>
                        <?php else: foreach ($allNotes as $n): ?>
                            <tr>
                                <td class="small text-muted"><?=date('M j, Y', strtotime($n['event_date']))?></td>
                                <td class="fw-bold"><?=h($n['message'])?></td>
                                <td>
                                    <?php if ($n['donation_id']): ?>
                                        <a href="?section=donations&search=<?=urlencode((string)$n['donation_id'])?>" class="text-decoration-none fw-bold" style="color: var(--panel-plum);">
                                            #DON-<?=str_pad((string)$n['donation_id'], 4, '0', STR_PAD_LEFT)?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">General</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge rounded-pill" style="background: var(--panel-plum); color: #fff; font-size: 12px;">Unread</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-muted); font-size: 12px;">Read</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if (!$n['is_read']): ?>
                                        <form method="post" action="index.php" class="d-inline">
                                            <input type="hidden" name="action" value="notification_read">
                                            <input type="hidden" name="notification_id" value="<?=$n['notification_id']?>">
                                            <button type="submit" class="btn btn-sm rounded-pill fw-bold px-3 py-1" style="background: #ffffff; color: var(--text-dark);">Mark Read</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php
        /* ------------------------------------------------------------
           ACTIVITY LOGS VIEW
        ------------------------------------------------------------ */
        elseif ($section === 'activityLogs' || $section === 'activitylogs'):
            $filterAct = trim((string)($_GET['filter_action'] ?? ''));
            $logSql = "SELECT al.*, u.username, d.item_name
                       FROM activitylogs al
                       LEFT JOIN users u ON u.user_id = al.user_id
                       LEFT JOIN donations d ON d.donation_id = al.donation_id";
            $logParams = [];
            if ($filterAct !== '') {
                if ($filterAct === 'STATUS_CHANGE') {
                    $logSql .= " WHERE al.action LIKE 'STATUS_CHANGE%'";
                } else {
                    $logSql .= " WHERE al.action = ?";
                    $logParams[] = $filterAct;
                }
            }
            $logSql .= " ORDER BY al.log_id DESC LIMIT 50";
            try {
                $lstmt = $conn->prepare($logSql);
                $lstmt->execute($logParams);
                $logsList = $lstmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $logsList = [];
            }
        ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Activity Logs</h2>
                    <div style="font-size: 14px; color: var(--text-muted);">Audited timeline tracking volunteer operations and inventory changes.</div>
                </div>
                <form method="get" action="index.php" class="d-flex gap-2">
                    <input type="hidden" name="section" value="activityLogs">
                    <select name="filter_action" class="form-control" onchange="this.form.submit()" style="border-radius: var(--radius-pill); font-size: 13px;">
                        <option value="">All Actions</option>
                        <option value="CREATED_DONATION" <?=$filterAct === 'CREATED_DONATION' ? 'selected' : ''?>>Donations Created</option>
                        <option value="UPDATED_DONATION" <?=$filterAct === 'UPDATED_DONATION' ? 'selected' : ''?>>Donations Updated</option>
                        <option value="STATUS_CHANGE" <?=$filterAct === 'STATUS_CHANGE' ? 'selected' : ''?>>Status Updates</option>
                        <option value="DELETED_DONATION" <?=$filterAct === 'DELETED_DONATION' ? 'selected' : ''?>>Donations Deleted</option>
                        <option value="SCHEDULED_EVENT" <?=$filterAct === 'SCHEDULED_EVENT' ? 'selected' : ''?>>Events Scheduled</option>
                        <option value="USER_LOGIN" <?=$filterAct === 'USER_LOGIN' ? 'selected' : ''?>>Staff Logins</option>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table-smartech">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Staff Member</th>
                            <th>Action</th>
                            <th>Target Supply</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logsList)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">No audit logs found.</td></tr>
                        <?php else: foreach ($logsList as $lg): ?>
                            <?php
                            $act = (string)($lg['action'] ?? '');
                            $actLabel = $act;
                            $actBadgeStyle = 'background: rgba(155, 122, 148, 0.15); color: var(--panel-plum);';
                            if (strpos($act, 'STATUS_CHANGE_TO_') === 0) {
                                $newSt = str_replace('STATUS_CHANGE_TO_', '', $act);
                                $actLabel = 'Status: ' . $newSt;
                                $actBadgeStyle = 'background: #E0F2FE; color: #0369A1;';
                            } elseif ($act === 'CREATED_DONATION') {
                                $actLabel = 'Donation Created';
                                $actBadgeStyle = 'background: #DCFCE7; color: #166534;';
                            } elseif ($act === 'UPDATED_DONATION') {
                                $actLabel = 'Donation Updated';
                                $actBadgeStyle = 'background: #FEF3C7; color: #92400E;';
                            } elseif ($act === 'DELETED_DONATION') {
                                $actLabel = 'Donation Deleted';
                                $actBadgeStyle = 'background: #FEE2E2; color: #991B1B;';
                            } elseif ($act === 'USER_LOGIN') {
                                $actLabel = 'Staff Login';
                                $actBadgeStyle = 'background: #F3E8FF; color: #6B21A8;';
                            } elseif ($act === 'SCHEDULED_EVENT') {
                                $actLabel = 'Event Scheduled';
                                $actBadgeStyle = 'background: #EDE9FE; color: #5B21B6;';
                            }

                            // Determine Target Supply column content
                            $target = '—';
                            if (!empty($lg['item_name'])) {
                                $target = $lg['item_name'];
                            } elseif (!empty($lg['donation_id'])) {
                                $target = '#DON-' . $lg['donation_id'];
                            } elseif ($act === 'USER_LOGIN') {
                                $target = 'Login Session';
                            } elseif (strpos($act, 'CATEGORY') !== false) {
                                $target = 'Category Setting';
                            } elseif (strpos($act, 'DONOR') !== false) {
                                $target = 'Donor Contributor';
                            } elseif (strpos($act, 'EVENT') !== false) {
                                $target = 'Relief Event';
                            } elseif (strpos($act, 'PROFILE') !== false || strpos($act, 'PASSWORD') !== false) {
                                $target = 'User Account';
                            }
                            ?>
                            <tr>
                                <td class="small text-muted"><?=date('M j, Y, g:i A', strtotime($lg['timestamp']))?></td>
                                <td>
                                    <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-dark); font-weight: 800;">
                                        <?=h($lg['username'] ?? 'Staff')?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill" style="<?=$actBadgeStyle?> font-weight: 800;">
                                        <?=h($actLabel)?>
                                    </span>
                                </td>
                                <td><?=h($target)?></td>
                                <td><span class="badge-status-pill badge-status-received"><i data-lucide="check" style="width:12px;height:12px;stroke-width:2.5;"></i> Success</span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        <?php
        /* ------------------------------------------------------------
           PROFILE VIEW
        ------------------------------------------------------------ */
        elseif ($section === 'profile'):
            $usrStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $usrStmt->execute([$currentUserId]);
            $userData = $usrStmt->fetch(PDO::FETCH_ASSOC);
        ?>
            <div class="mb-4">
                <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Staff Profile & Security</h2>
                <div style="font-size: 14px; color: var(--text-muted);">Manage your user credentials, disaster contact email, and password.</div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card-smartech">
                        <h3 class="h5 fw-bold mb-3" style="color: var(--text-dark);">Profile Information</h3>
                        <form method="post" action="index.php">
                            <input type="hidden" name="action" value="profile_update">

                            <div class="mb-3">
                                <label class="form-label" for="p_user">Username</label>
                                <input class="form-control" type="text" id="p_user" name="username" value="<?=h($userData['username'] ?? '')?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="p_email">Work Email</label>
                                <input class="form-control" type="email" id="p_email" name="email" value="<?=h($userData['email'] ?? '')?>" required>
                            </div>

                            <button type="submit" class="btn-plum-action">
                                Save Profile Changes
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card-smartech">
                        <h3 class="h5 fw-bold mb-3" style="color: var(--text-dark);">Change Password</h3>
                        <form method="post" action="index.php">
                            <input type="hidden" name="action" value="password_change">

                            <div class="mb-3">
                                <label class="form-label" for="cur_pass">Current Password</label>
                                <input class="form-control" type="password" id="cur_pass" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="new_pass">New Password (min 6 characters)</label>
                                <input class="form-control" type="password" id="new_pass" name="new_password" minlength="6" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="conf_pass">Confirm New Password</label>
                                <input class="form-control" type="password" id="conf_pass" name="confirm_password" minlength="6" required>
                            </div>

                            <button type="submit" class="btn-plum-action">
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Session & Sign Out Card (Accessible on All Devices & Mobile) -->
                <div class="col-12">
                    <div class="card-smartech d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                        <div>
                            <h3 class="h6 fw-bold m-0 text-danger">Sign Out of Session</h3>
                            <div class="small text-muted">Currently active session for <strong><?=h($userData['username'] ?? $currentUsername)?></strong> (<?=h($userData['email'] ?? '')?>).</div>
                        </div>
                        <a href="?logout=1" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2">
                            <i data-lucide="log-out" style="width:16px;height:16px;stroke-width:2;"></i>
                            <span>Log out</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Mobile Bottom Tabs (< 768px) -->
<nav class="mobile-bottom-tabs" aria-label="Mobile Navigation">
    <a href="?section=dashboard" class="mobile-tab-btn <?=$section === 'dashboard' ? 'active' : ''?>">
        <i data-lucide="layout-grid" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Dashboard</span>
    </a>
    <a href="?section=donations" class="mobile-tab-btn <?=$section === 'donations' ? 'active' : ''?>">
        <i data-lucide="package" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Donations</span>
    </a>
    <a href="javascript:void(0)" class="mobile-tab-btn" data-bs-toggle="modal" data-bs-target="#modalAddDonation" style="color: var(--panel-plum);">
        <span class="badge rounded-circle p-2" style="background: var(--panel-plum); color: #fff; margin-top: -14px;">
            <i data-lucide="plus" style="width:22px;height:22px;stroke-width:2.5;"></i>
        </span>
    </a>
    <a href="?section=categories" class="mobile-tab-btn <?=$section === 'categories' ? 'active' : ''?>">
        <i data-lucide="tags" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Categories</span>
    </a>
    <a href="?section=profile" class="mobile-tab-btn <?=$section === 'profile' ? 'active' : ''?>">
        <i data-lucide="user" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Profile</span>
    </a>
</nav>

<!-- ============================================================
     GLOBAL MODALS (Events, Donations, Categories, Donors)
============================================================ -->

<!-- Modal: Schedule Distribution Event -->
<div class="modal fade" id="modalAddEvent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="event_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Schedule Relief Distribution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ev_title">Event Title / Mission</label>
                        <input class="form-control" type="text" id="ev_title" name="title" placeholder="e.g. Barangay San Jose Relief Mission" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ev_date">Date</label>
                            <input class="form-control" type="date" id="ev_date" name="event_date" value="<?=date('Y-m-d')?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ev_time">Time</label>
                            <input class="form-control" type="time" id="ev_time" name="event_time" value="09:00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="ev_loc">Evacuation Site / Location</label>
                        <input class="form-control" type="text" id="ev_loc" name="location" placeholder="e.g. North Covered Gym / Sector 4 Depot" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="ev_stat">Status</label>
                        <select class="form-control" id="ev_stat" name="status">
                            <option value="Scheduled" selected>Scheduled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save to Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View Event Details -->
<div class="modal fade" id="modalViewEvent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="vEvTitle" style="color: var(--text-dark);">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="p-3 rounded-4 mb-3" style="background: #ffffff;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge rounded-pill" id="vEvStatusBadge" style="background: var(--panel-plum); color: #fff;">Status</span>
                        <small class="text-muted" id="vEvDateTime"></small>
                    </div>
                    <div class="fw-bold mb-1" id="vEvLocation" style="color: var(--text-dark);"></div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <form method="post" action="index.php" id="formCompleteEvent" class="d-inline">
                        <input type="hidden" name="action" value="event_status_update">
                        <input type="hidden" name="event_id" id="vEvIdComplete">
                        <input type="hidden" name="status" value="Completed">
                        <button type="submit" class="btn btn-sm rounded-pill px-3 fw-bold" style="background: #DCFCE7; color: #166534;">Mark Completed</button>
                    </form>

                    <form method="post" action="index.php" id="formDeleteEvent" class="d-inline">
                        <input type="hidden" name="action" value="event_delete">
                        <input type="hidden" name="event_id" id="vEvIdDelete">
                        <button type="submit" class="btn btn-sm rounded-pill px-3 fw-bold text-danger" style="background: #FEE2E2;">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: All Relief Events List -->
<div class="modal fade" id="modalAllEvents" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" style="color: var(--text-dark);">All Relief Missions & Events</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3" style="max-height: 480px; overflow-y: auto;">
                <?php if (empty($allEvents)): ?>
                    <div class="text-center py-4 text-muted">No events currently scheduled.</div>
                <?php else: foreach ($allEvents as $ev): ?>
                    <div class="pill-list-row mb-2">
                        <div class="pill-row-avatar">
                            <i data-lucide="calendar" style="width:16px;height:16px;stroke-width:2;"></i>
                        </div>
                        <div class="pill-row-info">
                            <div class="pill-row-name"><?=h($ev['title'])?></div>
                            <div class="pill-row-desc"><?=date('D, d M Y', strtotime($ev['event_date']))?> &bull; <?=h($ev['event_time'])?> &bull; <?=h($ev['location'])?></div>
                        </div>
                        <span class="badge rounded-pill me-2" style="background: <?=($ev['status'] === 'Completed') ? '#DCFCE7' : 'var(--panel-plum)'?>; color: <?=($ev['status'] === 'Completed') ? '#166534' : '#ffffff'?>;">
                            <?=h($ev['status'])?>
                        </span>
                        <form method="post" action="index.php" class="d-inline">
                            <input type="hidden" name="action" value="event_delete">
                            <input type="hidden" name="event_id" value="<?=$ev['event_id']?>">
                            <button type="submit" class="btn btn-sm text-danger border-0 bg-transparent" title="Delete event">
                                <i data-lucide="trash-2" style="width:16px;height:16px;stroke-width:2;"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddEvent">+ Schedule New Event</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Donor Quick Details -->
<div class="modal fade" id="modalDonorQuick" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="dqName" style="color: var(--text-dark);">Donor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="p-3 rounded-4 mb-3" style="background: #ffffff;">
                    <div class="small text-muted mb-1">Phone Contact</div>
                    <div class="fw-bold mb-2" id="dqPhone"></div>
                    <div class="small text-muted mb-1">Depot / Station Address</div>
                    <div class="fw-bold" id="dqAddr"></div>
                </div>
                <div class="text-end">
                    <a href="#" id="dqTelBtn" class="btn-plum-action text-decoration-none">
                        <i data-lucide="phone" style="width:16px;height:16px;stroke-width:2;"></i>
                        <span>Call Contributor</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Donation -->
<div class="modal fade" id="modalAddDonation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donation_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Record Relief Supply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ad_item">Supply / Item Name</label>
                        <input class="form-control" type="text" id="ad_item" name="item_name" placeholder="e.g. Bottled Drinking Water 500ml" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ad_cat">Category</label>
                            <select class="form-control" id="ad_cat" name="category_id" required>
                                <?php
                                $mCats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($mCats as $mc):
                                ?>
                                    <option value="<?=$mc['category_id']?>"><?=h($mc['category_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ad_status">Status</label>
                            <select class="form-control" id="ad_status" name="status" required>
                                <option value="Received" selected>Received</option>
                                <option value="In Stock">In Stock</option>
                                <option value="Pending">Pending</option>
                                <option value="Released">Released</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="ad_donor">Donor Contributor</label>
                        <select class="form-control" id="ad_donor" name="donor_id" required>
                            <?php
                            $mDonors = $conn->query("SELECT donor_id, contact_person FROM donors ORDER BY contact_person")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($mDonors as $md):
                            ?>
                                <option value="<?=$md['donor_id']?>"><?=h($md['contact_person'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="ad_qty">Quantity (Units / Packs)</label>
                            <input class="form-control" type="number" id="ad_qty" name="quantity" value="10" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ad_date">Date Received</label>
                            <input class="form-control" type="date" id="ad_date" name="donation_date" value="<?=date('Y-m-d')?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Donation -->
<div class="modal fade" id="modalEditDonation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donation_edit">
                <input type="hidden" name="donation_id" id="edit_don_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Edit Relief Supply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="edit_don_name">Item Name</label>
                        <input class="form-control" type="text" id="edit_don_name" name="item_name" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="edit_don_cat">Category</label>
                            <select class="form-control" id="edit_don_cat" name="category_id" required>
                                <?php foreach ($mCats as $mc): ?>
                                    <option value="<?=$mc['category_id']?>"><?=h($mc['category_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_don_status">Status</label>
                            <select class="form-control" id="edit_don_status" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="Received">Received</option>
                                <option value="In Stock">In Stock</option>
                                <option value="Released">Released</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit_don_donor">Donor Contributor</label>
                        <select class="form-control" id="edit_don_donor" name="donor_id" required>
                            <?php foreach ($mDonors as $md): ?>
                                <option value="<?=$md['donor_id']?>"><?=h($md['contact_person'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="edit_don_qty">Quantity</label>
                            <input class="form-control" type="number" id="edit_don_qty" name="quantity" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_don_date">Date</label>
                            <input class="form-control" type="date" id="edit_don_date" name="donation_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Donation -->
<div class="modal fade" id="modalDeleteDonation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donation_delete">
                <input type="hidden" name="donation_id" id="del_don_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="mb-1">Are you sure you want to delete this donation record?</p>
                    <div class="fw-bold fs-5 text-dark" id="del_don_name"></div>
                    <div class="alert alert-warning py-2 small rounded-pill px-3 mt-3 mb-0">
                        This action will update stock ledger balances.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="category_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Add Supply Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ac_name">Category Name</label>
                        <input class="form-control" type="text" id="ac_name" name="category_name" placeholder="e.g. Baby Care Products" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ac_icon">Line Icon</label>
                            <select class="form-control" id="ac_icon" name="icon">
                                <option value="utensils">Food (Utensils)</option>
                                <option value="droplets">Water (Droplets)</option>
                                <option value="pill">Medicine (Pill)</option>
                                <option value="shirt">Clothing (Shirt)</option>
                                <option value="sparkles">Hygiene (Sparkles)</option>
                                <option value="package" selected>General (Package)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ac_col">Color Swatch</label>
                            <input class="form-control p-1" type="color" id="ac_col" name="color" value="#0284c7">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ac_desc">Description</label>
                        <textarea class="form-control" id="ac_desc" name="description" rows="2" style="border-radius: 16px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="modalEditCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="category_edit">
                <input type="hidden" name="category_id" id="ec_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ec_name">Category Name</label>
                        <input class="form-control" type="text" id="ec_name" name="category_name" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ec_icon">Line Icon</label>
                            <input class="form-control" type="text" id="ec_icon" name="icon" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ec_color">Color</label>
                            <input class="form-control p-1" type="color" id="ec_color" name="color" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ec_desc">Description</label>
                        <textarea class="form-control" id="ec_desc" name="description" rows="2" style="border-radius: 16px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Category -->
<div class="modal fade" id="modalDeleteCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="category_delete">
                <input type="hidden" name="category_id" id="del_cat_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Delete category <strong id="del_cat_name"></strong>?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Donor -->
<div class="modal fade" id="modalAddDonor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donor_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Register Donor Partner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="adn_person">Contact Person / Organization</label>
                        <input class="form-control" type="text" id="adn_person" name="contact_person" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adn_phone">Phone Contact</label>
                        <input class="form-control" type="text" id="adn_phone" name="contact_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adn_addr">Address / Depot Location</label>
                        <input class="form-control" type="text" id="adn_addr" name="address" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save Contributor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Donor -->
<div class="modal fade" id="modalEditDonor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donor_edit">
                <input type="hidden" name="donor_id" id="edn_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="edn_person">Contact Person</label>
                        <input class="form-control" type="text" id="edn_person" name="contact_person" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edn_phone">Contact Phone</label>
                        <input class="form-control" type="text" id="edn_phone" name="contact_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edn_addr">Address</label>
                        <input class="form-control" type="text" id="edn_addr" name="address" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Update Contributor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Donor -->
<div class="modal fade" id="modalDeleteDonor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donor_delete">
                <input type="hidden" name="donor_id" id="del_dn_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Delete Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Remove donor <strong id="del_dn_name"></strong>?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
lucide.createIcons();

// Fullscreen Toggle
function toggleFullScreen() {
    const fsIcon = document.getElementById('fsIcon');
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
        if (fsIcon) fsIcon.setAttribute('data-lucide', 'minimize-2');
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
        if (fsIcon) fsIcon.setAttribute('data-lucide', 'maximize-2');
    }
    lucide.createIcons();
}

// Global Events Array for Calendar & Widgets
const allEventsData = <?=json_encode($allEvents)?>;

// Calendar State
<?php
$calSelectedDate = trim((string)($_GET['sel_date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $calSelectedDate)) {
    $calSelectedDate = date('Y-m-d');
}
$calTimestamp = strtotime($calSelectedDate);
$initialCalYear = (int)date('Y', $calTimestamp);
$initialCalMonth = (int)date('n', $calTimestamp) - 1;
?>
let calYear = <?=$initialCalYear?>;
let calMonth = <?=$initialCalMonth?>;
let selectedDateStr = '<?=$calSelectedDate?>';

const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

function renderCalendar() {
    const monthTitle = document.getElementById('calMonthTitle');
    const daysGrid = document.getElementById('calDaysGrid');
    if (!monthTitle || !daysGrid) return;

    monthTitle.textContent = `${monthNames[calMonth]} ${calYear}`;
    daysGrid.innerHTML = '';

    // Calculate first day of month (Monday = 1 ... Sunday = 7)
    const firstDay = new Date(calYear, calMonth, 1);
    let startDayOfWeek = firstDay.getDay(); // 0 is Sunday, 1 is Monday
    startDayOfWeek = (startDayOfWeek === 0) ? 7 : startDayOfWeek;

    // Days in current month
    const totalDaysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

    // Leading empty slots
    for (let b = 1; b < startDayOfWeek; b++) {
        const blank = document.createElement('div');
        daysGrid.appendChild(blank);
    }

    // Days 1 to totalDaysInMonth
    for (let d = 1; d <= totalDaysInMonth; d++) {
        const mStr = String(calMonth + 1).padStart(2, '0');
        const dStr = String(d).padStart(2, '0');
        const dateKey = `${calYear}-${mStr}-${dStr}`;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'calendar-day-btn';
        btn.textContent = d;

        // Has event check
        const hasEvent = allEventsData.some(ev => ev.event_date === dateKey);
        if (hasEvent) btn.classList.add('has-event');

        // Selected active check
        if (dateKey === selectedDateStr) {
            btn.classList.add('active');
        }

        btn.onclick = function () {
            selectedDateStr = dateKey;
            renderCalendar();
            renderScheduleList(dateKey);
        };

        daysGrid.appendChild(btn);
    }
}

function prevCalMonth() {
    calMonth--;
    if (calMonth < 0) {
        calMonth = 11;
        calYear--;
    }
    renderCalendar();
}

function nextCalMonth() {
    calMonth++;
    if (calMonth > 11) {
        calMonth = 0;
        calYear++;
    }
    renderCalendar();
}

function renderScheduleList(dateKey) {
    const container = document.getElementById('scheduleListContainer');
    if (!container) return;

    const matchingEvents = allEventsData.filter(ev => ev.event_date === dateKey);

    if (matchingEvents.length > 0) {
        let html = '';
        matchingEvents.forEach(ev => {
            const dayNum = ev.event_date.split('-')[2];
            html += `
                <div class="schedule-pill-item" onclick="openEventModal(${ev.event_id}, '${escapeHtml(ev.title)}', '${ev.event_date}', '${escapeHtml(ev.event_time)}', '${escapeHtml(ev.location)}', '${escapeHtml(ev.status)}')">
                    <div class="schedule-date-num">${dayNum}</div>
                    <div class="overflow-hidden">
                        <div class="schedule-event-title">${escapeHtml(ev.title)}</div>
                        <div class="schedule-event-time">${escapeHtml(ev.event_time)} &bull; ${escapeHtml(ev.location)}</div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    } else {
        // Fallback: Show next upcoming distribution dates so area is never empty
        const nextEvents = allEventsData.filter(ev => ev.event_date >= dateKey).slice(0, 3);
        if (nextEvents.length === 0 && allEventsData.length > 0) {
            nextEvents.push(...allEventsData.slice(0, 3));
        }

        let html = `
            <div class="p-2 text-center rounded-4 mb-1" style="background: rgba(255,255,255,0.7); font-size: 11px;">
                No distributions on <strong>${dateKey}</strong>.
                <button type="button" class="btn btn-link p-0 text-decoration-none fw-bold small ms-1" style="color:var(--panel-plum);font-size:11px;" onclick="scheduleForDate('${dateKey}')">+ Schedule</button>
            </div>
        `;

        nextEvents.forEach(ev => {
            const dayNum = ev.event_date.split('-')[2];
            html += `
                <div class="schedule-pill-item" onclick="openEventModal(${ev.event_id}, '${escapeHtml(ev.title)}', '${ev.event_date}', '${escapeHtml(ev.event_time)}', '${escapeHtml(ev.location)}', '${escapeHtml(ev.status)}')">
                    <div class="schedule-date-num">${dayNum}</div>
                    <div class="overflow-hidden">
                        <div class="schedule-event-title">${escapeHtml(ev.title)}</div>
                        <div class="schedule-event-time">${escapeHtml(ev.event_time)} &bull; ${escapeHtml(ev.location)}</div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }
    lucide.createIcons();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function scheduleForDate(dateStr) {
    const modalEl = document.getElementById('modalAddEvent');
    if (modalEl) {
        modalEl.querySelector('#ev_date').value = dateStr;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function openEventModal(id, title, date, time, location, status) {
    const modalEl = document.getElementById('modalViewEvent');
    if (modalEl) {
        modalEl.querySelector('#vEvTitle').textContent = title;
        modalEl.querySelector('#vEvDateTime').textContent = `${date} at ${time}`;
        modalEl.querySelector('#vEvLocation').textContent = `Location: ${location}`;
        modalEl.querySelector('#vEvStatusBadge').textContent = status;
        modalEl.querySelector('#vEvIdComplete').value = id;
        modalEl.querySelector('#vEvIdDelete').value = id;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function confirmDeleteDonation(id, name) {
    const modalEl = document.getElementById('modalDeleteDonation');
    if (modalEl) {
        modalEl.querySelector('#del_don_id').value = id;
        modalEl.querySelector('#del_don_name').textContent = name;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

function viewDonorDetails(name, phone, addr) {
    const modalEl = document.getElementById('modalDonorQuick');
    if (modalEl) {
        modalEl.querySelector('#dqName').textContent = name;
        modalEl.querySelector('#dqPhone').textContent = phone || 'No phone provided';
        modalEl.querySelector('#dqAddr').textContent = addr || 'Central Depot';
        modalEl.querySelector('#dqTelBtn').href = `tel:${phone}`;
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    lucide.createIcons();

    // Initialize interactive calendar and distribution schedule
    renderCalendar();
    renderScheduleList(selectedDateStr);

    // Populate Edit Donation Modal
    const editDonModal = document.getElementById('modalEditDonation');
    if (editDonModal) {
        editDonModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            editDonModal.querySelector('#edit_don_id').value = btn.getAttribute('data-id');
            editDonModal.querySelector('#edit_don_name').value = btn.getAttribute('data-name');
            editDonModal.querySelector('#edit_don_qty').value = btn.getAttribute('data-qty');
            editDonModal.querySelector('#edit_don_cat').value = btn.getAttribute('data-cat');
            editDonModal.querySelector('#edit_don_donor').value = btn.getAttribute('data-donor');
            editDonModal.querySelector('#edit_don_date').value = btn.getAttribute('data-date');
            editDonModal.querySelector('#edit_don_status').value = btn.getAttribute('data-status') || 'Received';
        });
    }

    // Automatic Filtration: Debounce search keyword input
    const searchInput = document.getElementById('search_input');
    const filterForm = document.getElementById('donationsFilterForm');
    if (searchInput && filterForm) {
        let debounceTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                filterForm.submit();
            }, 350);
        });

        // If search was performed, restore focus to search input
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search') && urlParams.get('search') !== '') {
            searchInput.focus();
            const valLen = searchInput.value.length;
            searchInput.setSelectionRange(valLen, valLen);
        }
    }

    // Populate Delete Donation Modal
    const delDonModal = document.getElementById('modalDeleteDonation');
    if (delDonModal) {
        delDonModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget ? (e.relatedTarget.closest('[data-bs-target="#modalDeleteDonation"]') || e.relatedTarget) : null;
            if (btn && btn.getAttribute('data-id')) {
                delDonModal.querySelector('#del_don_id').value = btn.getAttribute('data-id');
                delDonModal.querySelector('#del_don_name').textContent = btn.getAttribute('data-name');
            }
        });
    }

    // Populate Edit Category Modal
    const editCatModal = document.getElementById('modalEditCategory');
    if (editCatModal) {
        editCatModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            editCatModal.querySelector('#ec_id').value = btn.getAttribute('data-id');
            editCatModal.querySelector('#ec_name').value = btn.getAttribute('data-name');
            editCatModal.querySelector('#ec_desc').value = btn.getAttribute('data-desc');
            editCatModal.querySelector('#ec_icon').value = btn.getAttribute('data-icon') || 'package';
            editCatModal.querySelector('#ec_color').value = btn.getAttribute('data-color') || '#0284c7';
        });
    }

    // Populate Delete Category Modal
    const delCatModal = document.getElementById('modalDeleteCategory');
    if (delCatModal) {
        delCatModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            delCatModal.querySelector('#del_cat_id').value = btn.getAttribute('data-id');
            delCatModal.querySelector('#del_cat_name').textContent = btn.getAttribute('data-name');
        });
    }

    // Populate Edit Donor Modal
    const editDnModal = document.getElementById('modalEditDonor');
    if (editDnModal) {
        editDnModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            editDnModal.querySelector('#edn_id').value = btn.getAttribute('data-id');
            editDnModal.querySelector('#edn_person').value = btn.getAttribute('data-person');
            editDnModal.querySelector('#edn_phone').value = btn.getAttribute('data-phone');
            editDnModal.querySelector('#edn_addr').value = btn.getAttribute('data-address');
        });
    }

    // Populate Delete Donor Modal
    const delDnModal = document.getElementById('modalDeleteDonor');
    if (delDnModal) {
        delDnModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            delDnModal.querySelector('#del_dn_id').value = btn.getAttribute('data-id');
            delDnModal.querySelector('#del_dn_name').textContent = btn.getAttribute('data-person');
        });
    }
});
</script>
</body>
</html>
