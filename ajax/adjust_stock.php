<?php
/**
 * MedWell Pharmacy - AJAX: Adjust Stock
 *
 * Adjusts medicine stock quantity (stock in, stock out, or manual adjustment).
 * Validates medicine existence, updates quantity, and logs the inventory change.
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ─── CSRF Token Validation ────────────────────────────────────────────────
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

try {
    // ─── Input Parameters ──────────────────────────────────────────────
    $medicineId = isset($_POST['medicine_id']) ? (int) $_POST['medicine_id'] : 0;
    $type       = trim($_POST['type'] ?? '');
    $quantity   = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $notes      = trim($_POST['notes'] ?? '');
    $reference  = trim($_POST['reference'] ?? '');

    // ─── Validate Inputs ───────────────────────────────────────────────
    if ($medicineId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid medicine ID.']);
        exit;
    }

    $validTypes = ['in', 'out', 'adjustment'];
    if (!in_array($type, $validTypes, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid adjustment type. Must be: in, out, or adjustment.']);
        exit;
    }

    if ($quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1.']);
        exit;
    }

    // ─── Verify Medicine Exists ────────────────────────────────────────
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, name, quantity FROM medicines WHERE id = :id AND is_active = 1'
    );
    $stmt->execute([':id' => $medicineId]);
    $medicine = $stmt->fetch();

    if ($medicine === false) {
        echo json_encode(['success' => false, 'message' => 'Medicine not found or inactive.']);
        exit;
    }

    // ─── Calculate New Quantity ────────────────────────────────────────
    $currentQty = (int) $medicine['quantity'];

    $newQty = match ($type) {
        'in'         => $currentQty + $quantity,
        'out'        => $currentQty - $quantity,
        'adjustment' => $quantity, // For adjustment, quantity is the new absolute value
    };

    // Prevent negative stock
    if ($newQty < 0) {
        echo json_encode([
            'success' => false,
            'message' => "Insufficient stock. Current: {$currentQty}, Attempted to remove: {$quantity}.",
        ]);
        exit;
    }

    // ─── Update Quantity ───────────────────────────────────────────────
    $updateStmt = $db->prepare(
        'UPDATE medicines SET quantity = :qty WHERE id = :id'
    );
    $updateStmt->execute([
        ':qty' => $newQty,
        ':id'  => $medicineId,
    ]);

    // ─── Log Inventory Change ──────────────────────────────────────────
    $logNotes = $notes;
    if ($reference !== '') {
        $logNotes .= " [Ref: {$reference}]";
    }
    $logNotes = trim($logNotes);

    logInventory(
        $medicineId,
        $type,
        $currentQty,
        $newQty,
        'manual_adjustment',
        null,
        $logNotes
    );

    // ─── Regenerate CSRF Token ─────────────────────────────────────────
    regenerateCsrfToken();

    echo json_encode([
        'success'      => true,
        'new_quantity' => $newQty,
        'message'      => "Stock updated successfully for '{$medicine['name']}'. New quantity: {$newQty}.",
    ]);

} catch (PDOException $e) {
    error_log('adjust_stock.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adjusting stock.',
    ]);
} catch (Throwable $e) {
    error_log('adjust_stock.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
