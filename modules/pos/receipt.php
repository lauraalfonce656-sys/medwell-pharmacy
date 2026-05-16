<?php
/**
 * MedWell Pharmacy - Receipt Print Page
 * 
 * Clean, minimal receipt layout for printing.
 * Supports thermal receipt format (58mm/80mm width).
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Sale.class.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$saleId    = (int) ($_GET['id'] ?? 0);
$saleObj   = new Sale();
$settings  = getSettings();
$taxRate   = defined('TAX_RATE') ? TAX_RATE : 18;

// Fetch sale data
$sale  = $saleId > 0 ? $saleObj->getById($saleId) : null;
$items = $saleId > 0 ? $saleObj->getItems($saleId) : [];

if (!$sale) {
    $_SESSION['flash_message'] = 'Sale not found.';
    $_SESSION['flash_type']    = 'error';
    header('Location: /modules/pos/sales.php');
    exit;
}

$pharmacyName    = $settings['pharmacy_name'] ?? APP_NAME;
$pharmacyAddress = $settings['pharmacy_address'] ?? '123 Health Street, Medical City';
$pharmacyPhone   = $settings['pharmacy_phone'] ?? '(02) 1234-5678';
$pharmacyTin     = $settings['pharmacy_tin'] ?? '';
$receiptWidth    = $_GET['width'] ?? '80mm'; // 58mm or 80mm

$currentUser = getCurrentUser();
$cashierName = $currentUser['full_name'] ?? 'Cashier';
$customerName = $sale['customer_name'] ?? 'Walk-in Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= sanitize($sale['invoice_number'] ?? '') ?> - <?= sanitize($pharmacyName) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary: #7CB342;
            --primary-dark: #558B2F;
            --text-primary: #2d3436;
            --text-secondary: #636e72;
            --text-muted: #b2bec3;
            --border-color: #dfe6e9;
            --bg-body: #f5f7f0;
            --bg-card: #ffffff;
            --receipt-width: <?= $receiptWidth ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── Top Action Bar ─────────────────────────────────────── */
        .receipt-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .receipt-actions button,
        .receipt-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
        }
        .receipt-actions button:hover,
        .receipt-actions a:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(124, 179, 66, 0.06);
        }
        .receipt-actions .btn-print {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: none;
            box-shadow: 0 4px 15px rgba(124, 179, 66, 0.3);
        }
        .receipt-actions .btn-print:hover {
            box-shadow: 0 6px 20px rgba(124, 179, 66, 0.45);
            transform: translateY(-1px);
            color: #fff;
        }

        /* Width selector */
        .width-selector {
            display: flex;
            gap: 4px;
            padding: 3px;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1.5px solid var(--border-color);
        }
        .width-selector a {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            border: none;
            background: transparent;
            color: var(--text-muted);
            transition: all 0.3s ease;
        }
        .width-selector a:hover,
        .width-selector a.active {
            background: var(--primary);
            color: #fff;
        }

        /* ─── Receipt Container ──────────────────────────────────── */
        .receipt-container {
            width: var(--receipt-width);
            max-width: 100%;
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* Receipt Content */
        .receipt {
            padding: 24px 20px;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.6;
        }

        /* Header */
        .receipt-header {
            text-align: center;
            padding-bottom: 16px;
            border-bottom: 2px dashed var(--border-color);
        }
        .receipt-header .pharmacy-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 10px;
        }
        .receipt-header h2 {
            font-size: 16px;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 4px;
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.3px;
        }
        .receipt-header p {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        .receipt-header .tin {
            font-size: 10px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Divider */
        .receipt-divider {
            border: none;
            border-top: 1px dashed var(--border-color);
            margin: 12px 0;
        }
        .receipt-divider.double {
            border-top: 2px dashed var(--border-color);
        }

        /* Info rows */
        .receipt-info {
            margin: 0;
        }
        .receipt-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .receipt-info-row .info-label {
            color: var(--text-muted);
            font-size: 11px;
        }
        .receipt-info-row .info-value {
            font-weight: 600;
            font-size: 11px;
            color: var(--text-primary);
        }

        /* Items table */
        .receipt-items {
            width: 100%;
            border-collapse: collapse;
        }
        .receipt-items thead th {
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .receipt-items thead th:last-child,
        .receipt-items thead th:nth-child(3) {
            text-align: right;
        }
        .receipt-items thead th:nth-child(2) {
            text-align: center;
        }
        .receipt-items tbody td {
            padding: 6px 0;
            font-size: 11px;
            color: var(--text-primary);
            border-bottom: 1px dotted #f0f0f0;
            vertical-align: top;
        }
        .receipt-items tbody td:last-child,
        .receipt-items tbody td:nth-child(3) {
            text-align: right;
            font-weight: 500;
        }
        .receipt-items tbody td:nth-child(2) {
            text-align: center;
        }
        .receipt-items .item-name {
            font-weight: 600;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Totals */
        .receipt-totals {
            margin-top: 8px;
        }
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
        }
        .receipt-total-row .total-label {
            color: var(--text-secondary);
        }
        .receipt-total-row .total-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        .receipt-total-row.discount .total-value {
            color: #e74c3c;
        }
        .receipt-total-row.grand-total {
            font-size: 16px;
            font-weight: 800;
            padding: 10px 0;
            margin-top: 8px;
            border-top: 2px solid var(--text-primary);
        }
        .receipt-total-row.grand-total .total-label {
            color: var(--text-primary);
            font-weight: 800;
        }
        .receipt-total-row.grand-total .total-value {
            color: var(--primary-dark);
        }

        /* Payment section */
        .receipt-payment {
            margin-top: 8px;
        }

        /* Footer */
        .receipt-footer {
            text-align: center;
            padding-top: 16px;
            border-top: 2px dashed var(--border-color);
            margin-top: 16px;
        }
        .receipt-footer .thank-you {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 4px;
            font-family: 'Inter', sans-serif;
        }
        .receipt-footer .sub-text {
            font-size: 10px;
            color: var(--text-muted);
            margin-bottom: 2px;
        }
        .receipt-footer .barcode-placeholder {
            margin-top: 12px;
            display: flex;
            justify-content: center;
        }
        .receipt-footer .barcode-placeholder .barcode-lines {
            display: flex;
            gap: 1px;
            align-items: end;
            height: 30px;
        }
        .receipt-footer .barcode-placeholder .bar {
            background: var(--text-primary);
            width: 1.5px;
        }
        .receipt-footer .barcode-placeholder .bar.thin { height: 20px; }
        .receipt-footer .barcode-placeholder .bar.thick { height: 30px; }
        .receipt-footer .invoice-below-barcode {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 4px;
            letter-spacing: 0.1em;
        }

        /* ─── Print Styles ──────────────────────────────────────── */
        @media print {
            body {
                background: #fff;
                padding: 0;
                margin: 0;
            }
            .receipt-actions {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
                max-width: none;
            }
            .receipt {
                padding: 8px;
            }
        }

        /* 58mm specific */
        @media (max-width: 80mm) {
            .receipt {
                font-size: 10px;
                padding: 12px 10px;
            }
            .receipt-header h2 {
                font-size: 14px;
            }
            .receipt-items tbody td,
            .receipt-items thead th {
                font-size: 9px;
            }
            .receipt-total-row {
                font-size: 10px;
            }
            .receipt-total-row.grand-total {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar (hidden when printing) -->
<div class="receipt-actions">
    <button class="btn-print" onclick="window.print()">
        <i class="ri-printer-line"></i> Print Receipt
    </button>
    <a href="/modules/pos/sales.php">
        <i class="ri-arrow-left-line"></i> Back to Sales
    </a>
    <div class="width-selector">
        <a href="?id=<?= $saleId ?>&width=58mm" class="<?= $receiptWidth === '58mm' ? 'active' : '' ?>">58mm</a>
        <a href="?id=<?= $saleId ?>&width=80mm" class="<?= $receiptWidth === '80mm' ? 'active' : '' ?>">80mm</a>
    </div>
</div>

<!-- Receipt -->
<div class="receipt-container">
    <div class="receipt" id="receiptContent">

        <!-- Header -->
        <div class="receipt-header">
            <div class="pharmacy-logo">
                <i class="ri-capsule-line"></i>
            </div>
            <h2><?= sanitize($pharmacyName) ?></h2>
            <p><?= sanitize($pharmacyAddress) ?></p>
            <p>Tel: <?= sanitize($pharmacyPhone) ?></p>
            <?php if ($pharmacyTin): ?>
            <p class="tin">TIN: <?= sanitize($pharmacyTin) ?></p>
            <?php endif; ?>
        </div>

        <hr class="receipt-divider">

        <!-- Sale Info -->
        <div class="receipt-info">
            <div class="receipt-info-row">
                <span class="info-label">Invoice #:</span>
                <span class="info-value"><?= sanitize($sale['invoice_number'] ?? 'N/A') ?></span>
            </div>
            <div class="receipt-info-row">
                <span class="info-label">Date:</span>
                <span class="info-value"><?= formatDateTime($sale['created_at'] ?? '') ?></span>
            </div>
            <div class="receipt-info-row">
                <span class="info-label">Cashier:</span>
                <span class="info-value"><?= sanitize($sale['user_name'] ?? $cashierName) ?></span>
            </div>
            <div class="receipt-info-row">
                <span class="info-label">Customer:</span>
                <span class="info-value"><?= sanitize($customerName) ?></span>
            </div>
        </div>

        <hr class="receipt-divider">

        <!-- Items Table -->
        <table class="receipt-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="item-name" title="<?= sanitize($item['medicine_name'] ?? '') ?>"><?= sanitize($item['medicine_name'] ?? 'Unknown') ?></td>
                        <td><?= (int) ($item['quantity'] ?? 0) ?></td>
                        <td><?= formatCurrency((float) ($item['unit_price'] ?? 0)) ?></td>
                        <td><?= formatCurrency((float) ($item['total_price'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:var(--text-muted);">No items</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr class="receipt-divider double">

        <!-- Totals -->
        <div class="receipt-totals">
            <div class="receipt-total-row">
                <span class="total-label">Subtotal</span>
                <span class="total-value"><?= formatCurrency((float) ($sale['subtotal'] ?? 0)) ?></span>
            </div>
            <?php if ((float) ($sale['discount'] ?? 0) > 0): ?>
            <div class="receipt-total-row discount">
                <span class="total-label">Discount</span>
                <span class="total-value">-<?= formatCurrency((float) ($sale['discount'] ?? 0)) ?></span>
            </div>
            <?php endif; ?>
            <div class="receipt-total-row">
                <span class="total-label">Tax (<?= $taxRate ?>%)</span>
                <span class="total-value"><?= formatCurrency((float) ($sale['tax_amount'] ?? 0)) ?></span>
            </div>
            <div class="receipt-total-row grand-total">
                <span class="total-label">TOTAL</span>
                <span class="total-value"><?= formatCurrency((float) ($sale['total_amount'] ?? 0)) ?></span>
            </div>
        </div>

        <hr class="receipt-divider">

        <!-- Payment Method -->
        <div class="receipt-payment">
            <div class="receipt-total-row">
                <span class="total-label">Payment Method</span>
                <span class="total-value" style="text-transform:capitalize;"><?= sanitize($sale['payment_method'] ?? 'cash') ?></span>
            </div>
            <?php
            $paymentStatus = $sale['payment_status'] ?? 'paid';
            $statusColor = match($paymentStatus) {
                'paid' => '#27ae60',
                'partial' => '#f39c12',
                'refunded' => '#e74c3c',
                default => '#636e72'
            };
            ?>
            <div class="receipt-total-row">
                <span class="total-label">Status</span>
                <span class="total-value" style="color:<?= $statusColor ?>; text-transform:capitalize;"><?= sanitize($paymentStatus) ?></span>
            </div>
        </div>

        <hr class="receipt-divider double">

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="thank-you">Thank you for your purchase!</div>
            <div class="sub-text">Get well soon with <?= sanitize($pharmacyName) ?></div>
            <div class="sub-text">Your health is our priority</div>

            <!-- Decorative barcode -->
            <div class="barcode-placeholder">
                <div class="barcode-lines">
                    <?php for ($i = 0; $i < 40; $i++): ?>
                    <div class="bar <?= rand(0, 1) ? 'thick' : 'thin' ?>"></div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="invoice-below-barcode"><?= sanitize($sale['invoice_number'] ?? '') ?></div>
        </div>

    </div>
</div>

<!-- Auto-print script -->
<script>
    // Auto-print if requested
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('autoprint') === '1') {
        window.addEventListener('load', function() {
            setTimeout(() => window.print(), 500);
        });
    }
</script>

</body>
</html>
