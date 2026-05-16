<?php
/**
 * MedWell Pharmacy - AJAX: Complete Sale
 *
 * Processes POS sale completion: validates stock, creates sale record
 * with transaction, deducts stock, logs inventory, and adds loyalty points.
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
    $customerId    = isset($_POST['customer_id']) && $_POST['customer_id'] !== ''
        ? (int) $_POST['customer_id']
        : null;
    $itemsJson     = $_POST['items'] ?? '[]';
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $discountType  = $_POST['discount_type'] ?? 'none';      // none, percentage, fixed
    $discountValue = (float) ($_POST['discount_value'] ?? 0);
    $notes         = trim($_POST['notes'] ?? '');

    // ─── Validate Payment Method ───────────────────────────────────────
    $validPaymentMethods = ['cash', 'card', 'mobile'];
    if (!in_array($paymentMethod, $validPaymentMethods, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
        exit;
    }

    // ─── Parse Items JSON ──────────────────────────────────────────────
    $items = json_decode($itemsJson, true);
    if (!is_array($items) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items in the sale.']);
        exit;
    }

    // ─── Validate Each Item ────────────────────────────────────────────
    foreach ($items as $index => $item) {
        if (!isset($item['medicine_id'], $item['quantity'])) {
            echo json_encode([
                'success' => false,
                'message' => "Invalid item data at index {$index}.",
            ]);
            exit;
        }
        if ((int) $item['quantity'] < 1) {
            echo json_encode([
                'success' => false,
                'message' => "Invalid quantity for item at index {$index}.",
            ]);
            exit;
        }
    }

    // ─── Database Transaction ──────────────────────────────────────────
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    // Calculate subtotal and validate stock
    $subtotal   = 0.0;
    $saleItems  = [];

    foreach ($items as $item) {
        $medicineId = (int) $item['medicine_id'];
        $quantity   = (int) $item['quantity'];
        $discount   = (float) ($item['discount'] ?? 0);

        // Fetch medicine with row lock
        $medStmt = $db->prepare(
            'SELECT id, name, price, cost_price, quantity, batch_number
             FROM medicines
             WHERE id = :id AND is_active = 1
             FOR UPDATE'
        );
        $medStmt->execute([':id' => $medicineId]);
        $medicine = $medStmt->fetch();

        if ($medicine === false) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => "Medicine ID {$medicineId} not found or inactive.",
            ]);
            exit;
        }

        if ((int) $medicine['quantity'] < $quantity) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => "Insufficient stock for '{$medicine['name']}'. Available: {$medicine['quantity']}, Requested: {$quantity}.",
            ]);
            exit;
        }

        $unitPrice  = (float) $medicine['price'];
        $lineTotal  = ($unitPrice * $quantity) - $discount;
        $subtotal  += $lineTotal;

        $saleItems[] = [
            'medicine_id'  => $medicineId,
            'quantity'     => $quantity,
            'unit_price'   => $unitPrice,
            'cost_price'   => (float) $medicine['cost_price'],
            'discount'     => $discount,
            'total_price'  => $lineTotal,
            'batch_number' => $medicine['batch_number'],
            'qty_before'   => (int) $medicine['quantity'],
        ];
    }

    // ─── Calculate Discount ────────────────────────────────────────────
    $discountAmount = 0.0;
    if ($discountType === 'percentage' && $discountValue > 0) {
        $discountAmount = round($subtotal * ($discountValue / 100), 2);
    } elseif ($discountType === 'fixed' && $discountValue > 0) {
        $discountAmount = min($discountValue, $subtotal);
    }

    // ─── Calculate Tax ─────────────────────────────────────────────────
    $taxable     = $subtotal - $discountAmount;
    $taxAmount   = round($taxable * (TAX_RATE / 100), 2);
    $totalAmount = $taxable + $taxAmount;

    // ─── Generate Invoice Number ───────────────────────────────────────
    $invoiceNumber = generateInvoiceNumber();

    // ─── Insert Sale Header ────────────────────────────────────────────
    $userId = getCurrentUserId();

    $saleStmt = $db->prepare(
        'INSERT INTO sales
         (invoice_number, customer_id, user_id, subtotal, tax_amount,
          discount_amount, total_amount, payment_method, payment_status, notes, created_at)
         VALUES
         (:invoice_number, :customer_id, :user_id, :subtotal, :tax_amount,
          :discount_amount, :total_amount, :payment_method, :payment_status, :notes, NOW())'
    );
    $saleStmt->execute([
        ':invoice_number'  => $invoiceNumber,
        ':customer_id'     => $customerId,
        ':user_id'         => $userId,
        ':subtotal'        => $subtotal,
        ':tax_amount'      => $taxAmount,
        ':discount_amount' => $discountAmount,
        ':total_amount'    => $totalAmount,
        ':payment_method'  => $paymentMethod,
        ':payment_status'  => 'paid',
        ':notes'           => $notes,
    ]);

    $saleId = (int) $db->lastInsertId();

    // ─── Insert Sale Items & Deduct Stock ──────────────────────────────
    $itemStmt = $db->prepare(
        'INSERT INTO sale_items
         (sale_id, medicine_id, batch_number, quantity, unit_price, discount, total_price)
         VALUES
         (:sale_id, :medicine_id, :batch_number, :quantity, :unit_price, :discount, :total_price)'
    );

    foreach ($saleItems as $item) {
        $itemStmt->execute([
            ':sale_id'      => $saleId,
            ':medicine_id'  => $item['medicine_id'],
            ':batch_number' => $item['batch_number'],
            ':quantity'     => $item['quantity'],
            ':unit_price'   => $item['unit_price'],
            ':discount'     => $item['discount'],
            ':total_price'  => $item['total_price'],
        ]);

        // Deduct stock
        $qtyAfter = $item['qty_before'] - $item['quantity'];
        $updateStmt = $db->prepare(
            'UPDATE medicines SET quantity = :qty WHERE id = :id'
        );
        $updateStmt->execute([
            ':qty' => $qtyAfter,
            ':id'  => $item['medicine_id'],
        ]);

        // Log inventory movement
        logInventory(
            $item['medicine_id'],
            'sale',
            $item['qty_before'],
            $qtyAfter,
            'sale',
            $saleId,
            "Sold {$item['quantity']} units - Invoice {$invoiceNumber}"
        );
    }

    // ─── Insert Payment Record ─────────────────────────────────────────
    $payStmt = $db->prepare(
        'INSERT INTO payments (sale_id, amount, payment_method, payment_date, created_at)
         VALUES (:sale_id, :amount, :payment_method, NOW(), NOW())'
    );
    $payStmt->execute([
        ':sale_id'        => $saleId,
        ':amount'         => $totalAmount,
        ':payment_method' => $paymentMethod,
    ]);

    // ─── Add Loyalty Points (if customer selected) ─────────────────────
    if ($customerId !== null) {
        $loyaltyPoints = (int) floor($totalAmount / 1000); // 1 point per 1000 TZS
        if ($loyaltyPoints > 0) {
            $custStmt = $db->prepare(
                'UPDATE customers
                 SET loyalty_points = loyalty_points + :points,
                     total_purchases = total_purchases + :purchase_amount
                 WHERE id = :id'
            );
            $custStmt->execute([
                ':points'          => $loyaltyPoints,
                ':purchase_amount' => $totalAmount,
                ':id'              => $customerId,
            ]);
        } else {
            // Still update total_purchases even if no loyalty points earned
            $custStmt = $db->prepare(
                'UPDATE customers SET total_purchases = total_purchases + :purchase_amount WHERE id = :id'
            );
            $custStmt->execute([
                ':purchase_amount' => $totalAmount,
                ':id'              => $customerId,
            ]);
        }
    }

    // ─── Commit Transaction ────────────────────────────────────────────
    $db->commit();

    // ─── Regenerate CSRF Token ─────────────────────────────────────────
    regenerateCsrfToken();

    echo json_encode([
        'success'        => true,
        'sale_id'        => $saleId,
        'invoice_number' => $invoiceNumber,
        'message'        => 'Sale completed successfully.',
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('complete_sale.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while processing the sale.',
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('complete_sale.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred while processing the sale.',
    ]);
}
