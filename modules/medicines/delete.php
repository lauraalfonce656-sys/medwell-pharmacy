<?php
/**
 * MedWell Pharmacy - Delete Medicine (Backend Only)
 *
 * Handles POST requests to delete (soft or hard) a medicine.
 * Validates CSRF token, checks request method, and redirects
 * back to the listing with a flash message.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flashMessage('medicine_error', 'Method not allowed. Use POST to delete.', 'error');
    redirect('/modules/medicines/');
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    flashMessage('medicine_error', 'Invalid security token. Please try again.', 'error');
    redirect('/modules/medicines/');
}

// Get medicine ID
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flashMessage('medicine_error', 'Invalid medicine ID.', 'error');
    redirect('/modules/medicines/');
}

$medicineObj = new Medicine();

// Verify medicine exists
$medicine = $medicineObj->getById($id);
if (!$medicine) {
    flashMessage('medicine_error', 'Medicine not found.', 'error');
    redirect('/modules/medicines/');
}

// Determine delete type based on settings
$settings = getSettings();
$deleteType = $settings['delete_mode'] ?? 'soft'; // 'soft' or 'hard'

$medicineName = $medicine['name'];

if ($deleteType === 'soft') {
    // Soft delete: set is_active = 0
    $result = $medicineObj->softDelete($id);
    if ($result) {
        regenerateCsrfToken();
        flashMessage('medicine_success', "Medicine \"{$medicineName}\" has been deactivated successfully.", 'success');
    } else {
        flashMessage('medicine_error', "Failed to deactivate medicine \"{$medicineName}\". Please try again.", 'error');
    }
} else {
    // Hard delete: permanently remove from database
    // Check if medicine has been used in sales
    $recentSales = $medicineObj->getRecentSales($id, 1);
    if (!empty($recentSales)) {
        // Has sales records, do soft delete instead to preserve data integrity
        $result = $medicineObj->softDelete($id);
        if ($result) {
            regenerateCsrfToken();
            flashMessage('medicine_success', "Medicine \"{$medicineName}\" has been deactivated (has sales history, cannot permanently delete).", 'success');
        } else {
            flashMessage('medicine_error', "Failed to deactivate medicine \"{$medicineName}\".", 'error');
        }
    } else {
        // No sales records, safe to hard delete
        $result = $medicineObj->delete($id);
        if ($result) {
            regenerateCsrfToken();
            flashMessage('medicine_success', "Medicine \"{$medicineName}\" has been permanently deleted.", 'success');
        } else {
            flashMessage('medicine_error', "Failed to delete medicine \"{$medicineName}\". Please try again.", 'error');
        }
    }
}

// Redirect back to medicines listing
redirect('/modules/medicines/');
