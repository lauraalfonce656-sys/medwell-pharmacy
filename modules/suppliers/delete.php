<?php
/**
 * MedWell Pharmacy - Delete Supplier (Backend Only)
 *
 * Handles POST requests to delete (soft delete) a supplier.
 * Validates CSRF token, checks request method, and redirects
 * back to the listing with a flash message.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flashMessage('supplier_error', 'Method not allowed. Use POST to delete.', 'error');
    redirect('/modules/suppliers/');
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    flashMessage('supplier_error', 'Invalid security token. Please try again.', 'error');
    redirect('/modules/suppliers/');
}

// Get supplier ID
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flashMessage('supplier_error', 'Invalid supplier ID.', 'error');
    redirect('/modules/suppliers/');
}

$supplierObj = new Supplier();

// Verify supplier exists
$supplier = $supplierObj->getById($id);
if (!$supplier) {
    flashMessage('supplier_error', 'Supplier not found.', 'error');
    redirect('/modules/suppliers/');
}

$supplierName = $supplier['name'];

// Soft delete: set is_active = 0
$result = $supplierObj->update($id, ['is_active' => 0]);

if ($result) {
    regenerateCsrfToken();
    flashMessage('supplier_success', "Supplier \"{$supplierName}\" has been deactivated successfully.", 'success');
} else {
    flashMessage('supplier_error', "Failed to deactivate supplier \"{$supplierName}\". Please try again.", 'error');
}

// Redirect back to suppliers listing
redirect('/modules/suppliers/');
