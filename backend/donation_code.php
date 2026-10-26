<?php
declare(strict_types=1);

require_once __DIR__ . '/../connections.php';
require_once __DIR__ . '/../classes/Donation.php';

$donationClass = new Donation($conn);
$redirectUrl = '../index.php?section=donations';

function redirectWithMessage(string $url, string $status, string $message): never {
    $sep = str_contains($url, '?') ? '&' : '?';
    $location = $url . $sep . 'type=' . urlencode($status === 'error' ? 'danger' : $status) . '&message=' . urlencode($message);
    header('Location: ' . $location);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage($redirectUrl, 'info', 'No request was submitted.');
}

if (isset($_POST['addDonation'])) {
    $item_name = trim((string)($_POST['item_name'] ?? $_POST['item_id'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $donation_date = trim((string)($_POST['donation_date'] ?? date('Y-m-d')));
    $donor_id = (int)($_POST['donor_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? 'Received'));

    if ($item_name === '' || $quantity <= 0 || $donor_id <= 0 || $category_id <= 0) {
        redirectWithMessage($redirectUrl, 'error', 'Please fill in all required fields.');
    }

    if ($donationClass->addDonation($item_name, $quantity, $donation_date, $donor_id, $category_id, $status)) {
        redirectWithMessage($redirectUrl, 'success', 'Donation added successfully.');
    }
    redirectWithMessage($redirectUrl, 'error', 'Failed to add donation.');
}

if (isset($_POST['updateDonation'])) {
    $donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
    $item_name = trim((string)($_POST['item_name'] ?? $_POST['item_id'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $donation_date = trim((string)($_POST['donation_date'] ?? date('Y-m-d')));
    $donor_id = (int)($_POST['donor_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? 'Received'));

    if (!$donation_id || $item_name === '' || $quantity <= 0 || $donor_id <= 0 || $category_id <= 0) {
        redirectWithMessage($redirectUrl, 'error', 'Invalid details or missing ID.');
    }

    if ($donationClass->updateDonation($donation_id, $item_name, $quantity, $donation_date, $donor_id, $category_id, $status)) {
        redirectWithMessage($redirectUrl, 'success', 'Donation updated successfully.');
    }
    redirectWithMessage($redirectUrl, 'error', 'Failed to update donation.');
}

if (isset($_POST['deleteDonation'])) {
    $donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'participant_id', FILTER_VALIDATE_INT);

    if (!$donation_id) {
        redirectWithMessage($redirectUrl, 'error', 'Invalid donation ID.');
    }

    if ($donationClass->deleteDonation($donation_id)) {
        redirectWithMessage($redirectUrl, 'success', 'Donation deleted successfully.');
    }
    redirectWithMessage($redirectUrl, 'error', 'Failed to delete donation.');
}

redirectWithMessage($redirectUrl, 'info', 'No valid action requested.');
