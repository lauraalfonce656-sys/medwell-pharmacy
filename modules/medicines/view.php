<?php
/**
 * MedWell Pharmacy - Medicine Detail / View Page
 *
 * Premium detail view with medicine info card, stock history,
 * related sales, and quick actions.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'medicines';
$pageTitle = 'Medicine Details';

$medicineObj = new Medicine();

// Load medicine by ID
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('medicine_error', 'Invalid medicine ID.', 'error');
    redirect('/modules/medicines/');
}

$medicine = $medicineObj->getById($id);
if (!$medicine) {
    flashMessage('medicine_error', 'Medicine not found.', 'error');
    redirect('/modules/medicines/');
}

// Fetch related data
$inventoryLogs = $medicineObj->getInventoryLogs($id, 15);
$recentSales = $medicineObj->getRecentSales($id, 10);

// Calculate derived data
$qty = (int) ($medicine['quantity'] ?? 0);
$minLevel = (int) ($medicine['min_stock_level'] ?? 10);
$stockValue = $qty * (float) ($medicine['cost_price'] ?? 0);
$retailValue = $qty * (float) ($medicine['price'] ?? 0);
$profitMargin = ((float) ($medicine['price'] ?? 0) > 0 && (float) ($medicine['cost_price'] ?? 0) > 0)
    ? round((((float) $medicine['price'] - (float) $medicine['cost_price']) / (float) $medicine['price']) * 100, 1)
    : 0;

// Stock status
if ($qty === 0) {
    $stockStatus = ['text' => 'Out of Stock', 'class' => 'badge-danger', 'icon' => 'ri-close-circle-line'];
} elseif ($qty <= $minLevel) {
    $stockStatus = ['text' => 'Low Stock', 'class' => 'badge-warning', 'icon' => 'ri-alarm-warning-line'];
} else {
    $stockStatus = ['text' => 'In Stock', 'class' => 'badge-success', 'icon' => 'ri-checkbox-circle-line'];
}

// Expiry status
$expiryStatus = ['text' => 'N/A', 'class' => '', 'days' => null];
if (!empty($medicine['expiry_date']) && $medicine['expiry_date'] !== '0000-00-00') {
    $daysLeft = calculateExpiry($medicine['expiry_date']);
    $expiryStatus['days'] = $daysLeft;
    if ($daysLeft < 0) {
        $expiryStatus = ['text' => 'Expired', 'class' => 'expired', 'days' => $daysLeft, 'icon' => 'ri-close-circle-line'];
    } elseif ($daysLeft <= 30) {
        $expiryStatus = ['text' => 'Expiring Soon', 'class' => 'expiring-soon', 'days' => $daysLeft, 'icon' => 'ri-alarm-warning-line'];
    } elseif ($daysLeft <= 90) {
        $expiryStatus = ['text' => 'Near Expiry', 'class' => 'near-expiry', 'days' => $daysLeft, 'icon' => 'ri-time-line'];
    } else {
        $expiryStatus = ['text' => 'Safe', 'class' => 'safe', 'days' => $daysLeft, 'icon' => 'ri-shield-check-line'];
    }
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('medicine_error', 'Invalid security token.', 'error');
        redirect('/modules/medicines/view.php?id=' . $id);
    }

    $adjustQty = (int) ($_POST['adjust_quantity'] ?? 0);
    $adjustType = $_POST['adjust_type'] ?? 'add';
    $adjustNotes = trim($_POST['adjust_notes'] ?? '');

    if ($adjustQty <= 0) {
        flashMessage('medicine_error', 'Please enter a valid quantity.', 'error');
        redirect('/modules/medicines/view.php?id=' . $id);
    }

    $change = $adjustType === 'add' ? $adjustQty : -$adjustQty;
    $result = $medicineObj->updateStock($id, $change, 'adjustment');

    if ($result) {
        regenerateCsrfToken();
        flashMessage('medicine_success', "Stock adjusted successfully for \"{$medicine['name']}\".", 'success');
    } else {
        flashMessage('medicine_error', 'Failed to adjust stock.', 'error');
    }
    redirect('/modules/medicines/view.php?id=' . $id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title><?= $pageTitle ?> - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .view-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; flex-wrap: wrap; gap: 16px;
        }
        .view-header .header-left { display: flex; align-items: center; gap: 16px; }
        .view-header .back-btn {
            width: 42px; height: 42px; border-radius: var(--radius-md);
            border: 1px solid var(--border-color); background: var(--bg-card);
            color: var(--text-secondary); display: flex; align-items: center;
            justify-content: center; font-size: 1.2rem; cursor: pointer;
            transition: var(--transition); text-decoration: none;
        }
        .view-header .back-btn:hover {
            border-color: var(--primary); color: var(--primary); background: var(--primary-50);
        }
        .view-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
        .view-header .subtitle { font-size: 0.86rem; color: var(--text-muted); margin-top: 2px; }
        .view-header .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* Info Card */
        .info-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
            overflow: hidden; margin-bottom: 20px;
        }
        .info-card-banner {
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary-dark));
        }
        .info-card-body { padding: 28px; }

        /* Medicine Title Section */
        .med-title-section {
            display: flex; align-items: flex-start; gap: 20px;
            margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);
        }
        .med-title-icon {
            width: 64px; height: 64px; border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; flex-shrink: 0;
        }
        .med-title-info { flex: 1; }
        .med-title-info h3 { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
        .med-title-info .med-generic { font-size: 0.92rem; color: var(--text-secondary); font-style: italic; }
        .med-title-info .med-meta {
            display: flex; gap: 16px; margin-top: 10px; flex-wrap: wrap;
        }
        .med-title-info .med-meta span {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.82rem; color: var(--text-muted); font-weight: 500;
        }
        .med-title-info .med-meta span i { font-size: 0.9rem; color: var(--primary); }
        .med-title-badges { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }

        /* Status Badges */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; letter-spacing: 0.02em; white-space: nowrap;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .status-badge.badge-success { background: rgba(39,174,96,0.1); color: #27ae60; }
        .status-badge.badge-success::before { background: #27ae60; }
        .status-badge.badge-warning { background: rgba(243,156,18,0.1); color: #e67e22; }
        .status-badge.badge-warning::before { background: #f39c12; }
        .status-badge.badge-danger { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .status-badge.badge-danger::before { background: #e74c3c; }

        /* Expiry Badge */
        .expiry-status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; white-space: nowrap;
        }
        .expiry-status-badge.expired { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .expiry-status-badge.expiring-soon { background: rgba(243,156,18,0.1); color: #e67e22; }
        .expiry-status-badge.near-expiry { background: rgba(243,156,18,0.06); color: #f39c12; }
        .expiry-status-badge.safe { background: rgba(39,174,96,0.1); color: #27ae60; }

        /* Details Grid */
        .details-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 20px; margin-bottom: 24px;
        }
        .detail-item { }
        .detail-item .detail-label {
            font-size: 0.74rem; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;
        }
        .detail-item .detail-value {
            font-size: 0.92rem; font-weight: 600; color: var(--text-primary);
        }
        .detail-item .detail-value.price { color: var(--primary-dark); font-size: 1.05rem; font-weight: 700; }
        .detail-item .detail-value.muted { color: var(--text-muted); font-weight: 400; }

        /* Stock Progress */
        .stock-progress {
            width: 100%; height: 8px; background: var(--border-color);
            border-radius: 4px; overflow: hidden; margin-top: 8px;
        }
        .stock-progress-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

        /* Two Column Layout */
        .two-col-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* Section Card */
        .section-card {
            background: var(--bg-card); border-radius: var(--radius-md);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .section-card .section-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 22px; border-bottom: 1px solid var(--border-color);
            background: var(--primary-50);
        }
        .section-card .section-header h4 {
            font-size: 0.95rem; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 8px; margin: 0;
        }
        .section-card .section-header h4 i { color: var(--primary); font-size: 1.05rem; }
        .section-card .section-body { padding: 0; }

        /* Log Entry */
        .log-entry {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 22px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .log-entry:last-child { border-bottom: none; }
        .log-entry:hover { background: var(--primary-50); }
        .log-entry .log-icon {
            width: 34px; height: 34px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; flex-shrink: 0;
        }
        .log-entry .log-icon.type-in { background: rgba(39,174,96,0.1); color: #27ae60; }
        .log-entry .log-icon.type-out { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .log-entry .log-icon.type-adjustment { background: rgba(52,152,219,0.1); color: #3498db; }
        .log-entry .log-icon.type-expired { background: rgba(243,156,18,0.1); color: #f39c12; }
        .log-entry .log-info { flex: 1; min-width: 0; }
        .log-entry .log-type { font-size: 0.84rem; font-weight: 600; color: var(--text-primary); }
        .log-entry .log-meta { font-size: 0.74rem; color: var(--text-muted); margin-top: 2px; }
        .log-entry .log-qty { font-size: 0.88rem; font-weight: 700; text-align: right; min-width: 60px; }
        .log-entry .log-qty.qty-in { color: #27ae60; }
        .log-entry .log-qty.qty-out { color: #e74c3c; }

        /* Sale Entry */
        .sale-entry {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 22px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .sale-entry:last-child { border-bottom: none; }
        .sale-entry:hover { background: var(--primary-50); }
        .sale-entry .sale-icon {
            width: 34px; height: 34px; border-radius: 8px;
            background: rgba(124,179,66,0.1); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; flex-shrink: 0;
        }
        .sale-entry .sale-info { flex: 1; }
        .sale-entry .sale-invoice {
            font-size: 0.84rem; font-weight: 600; color: var(--primary);
        }
        .sale-entry .sale-customer { font-size: 0.76rem; color: var(--text-muted); margin-top: 2px; }
        .sale-entry .sale-amount { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); text-align: right; }

        /* Empty State */
        .empty-state {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 32px 20px; color: var(--text-muted); text-align: center;
        }
        .empty-state i { font-size: 2rem; margin-bottom: 8px; opacity: 0.3; }
        .empty-state p { font-size: 0.84rem; }

        /* Adjust Stock Modal */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 2000; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: var(--transition); padding: 20px;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-dialog {
            background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            width: 100%; max-width: 480px; max-height: 85vh; overflow: hidden;
            display: flex; flex-direction: column; transform: scale(0.9) translateY(20px);
            transition: var(--transition);
        }
        .modal-overlay.active .modal-dialog { transform: scale(1) translateY(0); }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; border-bottom: 1px solid var(--border-color);
        }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-close {
            width: 32px; height: 32px; border-radius: var(--radius-sm); border: none;
            background: transparent; color: var(--text-muted); font-size: 1.2rem;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .modal-close:hover { background: var(--primary-50); color: var(--danger); }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer {
            display: flex; align-items: center; justify-content: flex-end; gap: 10px;
            padding: 16px 24px; border-top: 1px solid var(--border-color);
        }

        @media (max-width: 1200px) {
            .details-grid { grid-template-columns: repeat(2, 1fr); }
            .two-col-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .details-grid { grid-template-columns: 1fr; }
            .med-title-section { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../../includes/templates/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/templates/header.php'; ?>

        <!-- Page Header -->
        <div class="view-header">
            <div class="header-left">
                <a href="/modules/medicines/" class="back-btn" title="Back to Medicines"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <h2>Medicine Details</h2>
                    <div class="subtitle">Viewing medicine #<?= $id ?></div>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()" title="Print Label">
                    <i class="ri-printer-line"></i> Print Label
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="openAdjustModal()" title="Adjust Stock">
                    <i class="ri-add-circle-line"></i> Adjust Stock
                </button>
                <a href="/modules/medicines/edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
                    <i class="ri-edit-line"></i> Edit
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php
        $flashSuccess = getFlashMessage('medicine_success');
        $flashError = getFlashMessage('medicine_error');
        ?>
        <?php if ($flashSuccess): ?>
        <div style="background: rgba(39,174,96,0.08); border: 1px solid rgba(39,174,96,0.2); border-radius: var(--radius-sm); padding: 12px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #27ae60; font-size: 0.88rem; font-weight: 500;">
            <i class="ri-check-line" style="font-size: 1.1rem;"></i>
            <?= sanitize($flashSuccess['message']) ?>
            <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #27ae60; cursor: pointer; font-size: 1rem;"><i class="ri-close-line"></i></button>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div style="background: rgba(231,76,60,0.08); border: 1px solid rgba(231,76,60,0.2); border-radius: var(--radius-sm); padding: 12px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #e74c3c; font-size: 0.88rem; font-weight: 500;">
            <i class="ri-error-warning-line" style="font-size: 1.1rem;"></i>
            <?= sanitize($flashError['message']) ?>
            <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 1rem;"><i class="ri-close-line"></i></button>
        </div>
        <?php endif; ?>

        <!-- Main Info Card -->
        <div class="info-card">
            <div class="info-card-banner"></div>
            <div class="info-card-body">
                <!-- Title Section -->
                <div class="med-title-section">
                    <div class="med-title-icon"><i class="ri-medicine-bottle-line"></i></div>
                    <div class="med-title-info">
                        <h3><?= sanitize($medicine['name']) ?></h3>
                        <?php if (!empty($medicine['generic_name'])): ?>
                        <div class="med-generic"><?= sanitize($medicine['generic_name']) ?></div>
                        <?php endif; ?>
                        <div class="med-meta">
                            <?php if (!empty($medicine['category_name'])): ?>
                            <span><i class="ri-folder-line"></i> <?= sanitize($medicine['category_name']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($medicine['brand'])): ?>
                            <span><i class="ri-price-tag-3-line"></i> <?= sanitize($medicine['brand']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($medicine['batch_number'])): ?>
                            <span><i class="ri-hashtag"></i> Batch: <?= sanitize($medicine['batch_number']) ?></span>
                            <?php endif; ?>
                            <span><i class="ri-stack-line"></i> <?= ucfirst($medicine['unit'] ?? 'tablet') ?></span>
                        </div>
                    </div>
                    <div class="med-title-badges">
                        <span class="status-badge <?= $stockStatus['class'] ?>">
                            <i class="<?= $stockStatus['icon'] ?>"></i> <?= $stockStatus['text'] ?>
                        </span>
                        <?php if ($expiryStatus['days'] !== null): ?>
                        <span class="expiry-status-badge <?= $expiryStatus['class'] ?>">
                            <i class="<?= $expiryStatus['icon'] ?? 'ri-time-line' ?>"></i>
                            <?= $expiryStatus['text'] ?>
                            <?php if ($expiryStatus['days'] >= 0): ?> (<?= $expiryStatus['days'] ?>d)<?php endif; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Selling Price</div>
                        <div class="detail-value price"><?= formatCurrency((float) ($medicine['price'] ?? 0)) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Cost Price</div>
                        <div class="detail-value"><?= formatCurrency((float) ($medicine['cost_price'] ?? 0)) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Profit Margin</div>
                        <div class="detail-value" style="color: <?= $profitMargin > 0 ? 'var(--success)' : 'var(--text-muted)' ?>;">
                            <?= $profitMargin ?>%
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Current Stock</div>
                        <div class="detail-value"><?= number_format($qty) ?> <?= ucfirst($medicine['unit'] ?? 'tablet') ?>s</div>
                        <?php
                        $stockPercent = $minLevel > 0 ? min(($qty / ($minLevel * 3)) * 100, 100) : 100;
                        $barColor = $qty === 0 ? 'var(--danger)' : ($qty <= $minLevel ? 'var(--warning)' : 'var(--success)');
                        ?>
                        <div class="stock-progress">
                            <div class="stock-progress-fill" style="width: <?= $stockPercent ?>%; background: <?= $barColor ?>;"></div>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Min Stock Level</div>
                        <div class="detail-value"><?= number_format($minLevel) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Stock Value (Cost)</div>
                        <div class="detail-value"><?= formatCurrency($stockValue) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Retail Value</div>
                        <div class="detail-value"><?= formatCurrency($retailValue) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Supplier</div>
                        <div class="detail-value"><?= sanitize($medicine['supplier_name'] ?? 'Not assigned') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Barcode</div>
                        <div class="detail-value" style="font-family: var(--font-mono, monospace); font-size: 0.88rem;">
                            <?= sanitize($medicine['barcode'] ?? 'N/A') ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Manufacture Date</div>
                        <div class="detail-value"><?= !empty($medicine['manufacture_date']) && $medicine['manufacture_date'] !== '0000-00-00' ? formatDate($medicine['manufacture_date']) : '<span class="muted">N/A</span>' ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Expiry Date</div>
                        <div class="detail-value">
                            <?php if (!empty($medicine['expiry_date']) && $medicine['expiry_date'] !== '0000-00-00'): ?>
                                <span style="color: <?= $expiryStatus['class'] === 'expired' ? '#e74c3c' : ($expiryStatus['class'] === 'expiring-soon' ? '#e67e22' : 'var(--text-primary)') ?>;">
                                    <?= formatDate($medicine['expiry_date']) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Created</div>
                        <div class="detail-value muted"><?= formatDateTime($medicine['created_at'] ?? '') ?></div>
                    </div>
                </div>

                <?php if (!empty($medicine['description'])): ?>
                <div style="padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <div class="detail-label" style="margin-bottom: 8px;">Description</div>
                    <p style="font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; margin: 0;"><?= nl2br(sanitize($medicine['description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Two Column: Stock History + Recent Sales -->
        <div class="two-col-layout">
            <!-- Stock History -->
            <div class="section-card">
                <div class="section-header">
                    <h4><i class="ri-history-line"></i> Stock History</h4>
                </div>
                <div class="section-body">
                    <?php if (!empty($inventoryLogs)): ?>
                        <?php foreach ($inventoryLogs as $log):
                            $logType = $log['type'] ?? 'adjustment';
                            $iconClass = match($logType) {
                                'in' => 'type-in',
                                'out' => 'type-out',
                                'expired' => 'type-expired',
                                default => 'type-adjustment',
                            };
                            $icon = match($logType) {
                                'in' => 'ri-arrow-down-line',
                                'out' => 'ri-arrow-up-line',
                                'expired' => 'ri-calendar-close-line',
                                default => 'ri-exchange-line',
                            };
                            $qtyChange = (int) $log['quantity_after'] - (int) $log['quantity_before'];
                            $qtyClass = $qtyChange >= 0 ? 'qty-in' : 'qty-out';
                        ?>
                        <div class="log-entry">
                            <div class="log-icon <?= $iconClass ?>"><i class="<?= $icon ?>"></i></div>
                            <div class="log-info">
                                <div class="log-type"><?= ucfirst($logType) ?></div>
                                <div class="log-meta">
                                    <?= $log['quantity_before'] ?> &rarr; <?= $log['quantity_after'] ?>
                                    &middot; <?= sanitize($log['user_name'] ?? 'System') ?>
                                    &middot; <?= formatDateTime($log['created_at'] ?? '') ?>
                                    <?php if (!empty($log['notes'])): ?><br><em><?= sanitize($log['notes']) ?></em><?php endif; ?>
                                </div>
                            </div>
                            <div class="log-qty <?= $qtyClass ?>"><?= $qtyChange >= 0 ? '+' : '' ?><?= $qtyChange ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="ri-history-line"></i>
                            <p>No stock history available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Sales -->
            <div class="section-card">
                <div class="section-header">
                    <h4><i class="ri-receipt-line"></i> Recent Sales</h4>
                </div>
                <div class="section-body">
                    <?php if (!empty($recentSales)): ?>
                        <?php foreach ($recentSales as $sale): ?>
                        <div class="sale-entry">
                            <div class="sale-icon"><i class="ri-receipt-line"></i></div>
                            <div class="sale-info">
                                <div class="sale-invoice"><?= sanitize($sale['invoice_number'] ?? '') ?></div>
                                <div class="sale-customer">
                                    <?= sanitize($sale['customer_name'] ?? 'Walk-in') ?>
                                    &middot; Qty: <?= number_format((int) $sale['quantity']) ?>
                                    &middot; <?= formatDateTime($sale['sale_date'] ?? '') ?>
                                </div>
                            </div>
                            <div class="sale-amount"><?= formatCurrency((float) ($sale['total_price'] ?? 0)) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="ri-receipt-line"></i>
                            <p>No sales recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>
    </main>
</div>

<!-- Adjust Stock Modal -->
<div class="modal-overlay" id="adjustStockModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><i class="ri-add-circle-line" style="color: var(--primary);"></i> Adjust Stock</h3>
            <button class="modal-close" onclick="closeAdjustModal()"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="adjust_stock" value="1">
            <div class="modal-body">
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 16px;">
                    Adjusting stock for <strong style="color: var(--text-primary);"><?= sanitize($medicine['name']) ?></strong>
                    <br>Current stock: <strong><?= number_format($qty) ?></strong>
                </p>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">Adjustment Type</label>
                    <select name="adjust_type" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.88rem; font-family: var(--font-sans); color: var(--text-primary); background: var(--bg-card); outline: none;">
                        <option value="add">Add Stock</option>
                        <option value="subtract">Remove Stock</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">Quantity</label>
                    <input type="number" name="adjust_quantity" min="1" value="1" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.88rem; font-family: var(--font-sans); color: var(--text-primary); background: var(--bg-card); outline: none;">
                </div>
                <div class="form-group">
                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">Notes</label>
                    <input type="text" name="adjust_notes" placeholder="Reason for adjustment..." style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.88rem; font-family: var(--font-sans); color: var(--text-primary); background: var(--bg-card); outline: none;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeAdjustModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="ri-check-line"></i> Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
function openAdjustModal() {
    document.getElementById('adjustStockModal').classList.add('active');
}
function closeAdjustModal() {
    document.getElementById('adjustStockModal').classList.remove('active');
}
</script>
</body>
</html>
