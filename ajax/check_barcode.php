<?php
/**
 * MedWell Pharmacy - AJAX: Check Barcode
 *
 * Checks if a barcode already exists in the medicines table.
 * Used for real-time validation during add/edit medicine forms.
 * Supports an optional exclude_id to skip the current record
 * when editing an existing medicine.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// ─── Authentication Check ─────────────────────────────────────────────────
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ─── Validate Request Method ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // ─── Input Parameters ──────────────────────────────────────────────
    $barcode   = trim($_GET['barcode'] ?? '');
    $excludeId = isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : 0;

    if ($barcode === '') {
        echo json_encode(['success' => false, 'message' => 'Barcode is required.']);
        exit;
    }

    // ─── Database Query ────────────────────────────────────────────────
    $db = Database::getInstance()->getConnection();

    if ($excludeId > 0) {
        // Exclude current record (for edit mode)
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS cnt
             FROM medicines
             WHERE barcode = :barcode AND id != :exclude_id'
        );
        $stmt->execute([
            ':barcode'    => $barcode,
            ':exclude_id' => $excludeId,
        ]);
    } else {
        // Normal check (add mode)
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS cnt
             FROM medicines
             WHERE barcode = :barcode'
        );
        $stmt->execute([':barcode' => $barcode]);
    }

    $count = (int) ($stmt->fetch()['cnt'] ?? 0);

    echo json_encode([
        'success' => true,
        'exists'  => $count > 0,
    ]);

} catch (PDOException $e) {
    error_log('check_barcode.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while checking the barcode.',
    ]);
} catch (Throwable $e) {
    error_log('check_barcode.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
