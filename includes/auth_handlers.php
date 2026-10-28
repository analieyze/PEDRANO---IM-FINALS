<?php
declare(strict_types=1);

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
