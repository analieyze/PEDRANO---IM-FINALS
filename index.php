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

// 2. DATABASE & CORE HELPERS
require_once __DIR__ . '/connections.php';
require_once __DIR__ . '/includes/helpers.php';

// 3. AUTHENTICATION ROUTING & HANDLERS
require_once __DIR__ . '/includes/auth_handlers.php';

// If user is not logged in, render the Guest Auth Screen and exit
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/screens/auth.php';
    exit;
}

// 4. AUTHENTICATED ACTIONS (POST / GET handlers)
require_once __DIR__ . '/includes/action_handlers.php';

// 5. GLOBAL DATA & METRICS
require_once __DIR__ . '/includes/global_data.php';

// 6. RENDER APPLICATION LAYOUT & ACTIVE SCREEN
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

echo '<main class="main-workspace">';
require_once __DIR__ . '/includes/navbar.php';

// Load the requested screen view
switch ($section) {
    case 'donations':
        require_once __DIR__ . '/screens/donations.php';
        break;
    case 'categories':
        require_once __DIR__ . '/screens/categories.php';
        break;
    case 'donors':
        require_once __DIR__ . '/screens/donors.php';
        break;
    case 'notifications':
        require_once __DIR__ . '/screens/notifications.php';
        break;
    case 'activityLogs':
    case 'activitylogs':
        require_once __DIR__ . '/screens/activity_logs.php';
        break;
    case 'profile':
        require_once __DIR__ . '/screens/profile.php';
        break;
    case 'dashboard':
    default:
        require_once __DIR__ . '/screens/dashboard.php';
        break;
}

echo '</main>';
echo '</div>'; // Closes .dashboard-container

require_once __DIR__ . '/includes/mobile_nav.php';
require_once __DIR__ . '/includes/modals.php';
require_once __DIR__ . '/includes/footer.php';
