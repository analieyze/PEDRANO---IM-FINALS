<?php
declare(strict_types=1);

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
