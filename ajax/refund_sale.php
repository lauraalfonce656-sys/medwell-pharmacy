<?php
/**
 * MedWell Pharmacy - AJAX: Refund Sale
 *
 * Processes a sale refund (admin only). Verifies the sale exists and
 * hasn't already been refunded, restores stock quantities, updates
 * the sale status, and logs inventory movements.
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

// ─── Admin-Only Access ────────────────────────────────────────────────────
if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin role required.']);
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
    $saleId = isset($_POST['sale_id']) ? (int) $_POST['sale_id'] : 0;

    if ($saleId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid sale ID.']);
        exit;
    }

    // ─── Verify Sale Exists and Not Already Refunded ───────────────────
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, invoice_number, customer_id, total_amount, payment_status
         FROM sales
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $saleId]);
    $sale = $stmt->fetch();

    if ($sale === false) {
        echo json_encode(['success' => false, 'message' => 'Sale not found.']);
        exit;
    }

    if ($sale['payment_status'] === 'refunded') {
        echo json_encode(['success' => false, 'message' => 'This sale has already been refunded.']);
        exit;
    }

    // ─── Begin Transaction ─────────────────────────────────────────────
    $db->beginTransaction();

    // ─── Update Sale Status ────────────────────────────────────────────
    $updateStmt = $db->prepare(
        'UPDATE sales SET payment_status = :status WHERE id = :id'
    );
    $updateStmt->execute([
        ':status' => 'refunded',
        ':id'     => $saleId,
    ]);

    // ─── Get Sale Items ────────────────────────────────────────────────
    $itemsStmt = $db->prepare(
        'SELECT medicine_id, quantity FROM sale_items WHERE sale_id = :sale_id'
    );
    $itemsStmt->execute([':sale_id' => $saleId]);
    $items = $itemsStmt->fetchAll();

    // ─── Restore Stock for Each Item ───────────────────────────────────
    foreach ($items as $item) {
        $medicineId = (int) $item['medicine_id'];
        $quantity   = (int) $item['quantity'];

        // Get current stock with row lock
        $medStmt = $db->prepare(
            'SELECT quantity FROM medicines WHERE id = :id FOR UPDATE'
        );
        $medStmt->execute([':id' => $medicineId]);
        $med = $medStmt->fetch();

        if ($med !== false) {
            $qtyBefore = (int) $med['quantity'];
            $qtyAfter  = $qtyBefore + $quantity;

            // Restore stock
            $restoreStmt = $db->prepare(
                'UPDATE medicines SET quantity = :qty WHERE id = :id'
            );
            $restoreStmt->execute([
                ':qty' => $qtyAfter,
                ':id'  => $medicineId,
            ]);

            // Log inventory movement
            logInventory(
                $medicineId,
                'in',
                $qtyBefore,
                $qtyAfter,
                'refund',
                $saleId,
                "Refund - Invoice {$sale['invoice_number']} - Restored {$quantity} units"
            );
        }
    }

    // ─── Deduct Loyalty Points if Customer Existed ─────────────────────
    if (!empty($sale['customer_id'])) {
        $pointsToDeduct = (int) floor((float) $sale['total_amount'] / 1000);
        if ($pointsToDeduct > 0) {
            $custStmt = $db->prepare(
                'UPDATE customers
                 SET loyalty_points = GREATEST(loyalty_points - :points, 0),
                     total_purchases = GREATEST(total_purchases - :amount, 0)
                 WHERE id = :id'
            );
            $custStmt->execute([
                ':points' => $pointsToDeduct,
                ':amount' => (float) $sale['total_amount'],
                ':id'     => $sale['customer_id'],
            ]);
        }
    }

    // ─── Commit Transaction ────────────────────────────────────────────
    $db->commit();

    // ─── Regenerate CSRF Token ─────────────────────────────────────────
    regenerateCsrfToken();

    echo json_encode([
        'success' => true,
        'message' => "Sale #{$sale['invoice_number']} has been refunded successfully.",
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('refund_sale.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while processing the refund.',
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('refund_sale.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred while processing the refund.',
    ]);
}
