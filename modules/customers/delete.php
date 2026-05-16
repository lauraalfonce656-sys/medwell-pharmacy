<?php
/**
 * Customer Soft Delete Handler
 * POST only, CSRF validated
 * Performs soft delete by setting is_active = 0
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = 'Invalid request method.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['flash_message'] = 'Invalid security token. Please try again.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

// Get and validate customer ID
$customerId = intval($_POST['customer_id'] ?? 0);
if ($customerId <= 0) {
    $_SESSION['flash_message'] = 'Invalid customer ID provided.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

try {
    $customerObj = new Customer();

    // Check if customer exists
    $customer = $customerObj->getById($customerId);
    if (!$customer) {
        $_SESSION['flash_message'] = 'Customer not found. It may have already been deleted.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: index.php');
        exit;
    }

    // Check if already inactive
    if (isset($customer['is_active']) && $customer['is_active'] == 0) {
        $_SESSION['flash_message'] = 'Customer "' . htmlspecialchars($customer['name']) . '" is already inactive.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: index.php');
        exit;
    }

    // Perform soft delete (set is_active = 0)
    $result = $customerObj->softDelete($customerId);

    if ($result) {
        $_SESSION['flash_message'] = 'Customer "' . htmlspecialchars($customer['name']) . '" has been deactivated successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Failed to deactivate customer. Please try again.';
        $_SESSION['flash_type'] = 'danger';
    }
} catch (Exception $e) {
    error_log("Customer delete error: " . $e->getMessage());
    $_SESSION['flash_message'] = 'An unexpected error occurred. Please try again later.';
    $_SESSION['flash_type'] = 'danger';
}

// Redirect back to customers list
header('Location: index.php');
exit;
