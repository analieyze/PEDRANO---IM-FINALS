<?php
declare(strict_types=1);

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
