<?php
/**
 * MedWell Pharmacy - Stock Adjustment Page
 *
 * Allows users to adjust medicine stock levels with:
 * Stock In, Stock Out, and Adjustment types.
 * Logs all changes to inventory_logs table.
 * CSRF protected.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'inventory';

$medicineObj = new Medicine();
$allMedicines = $medicineObj->getAll(['is_active' => 1], '', 5000, 0);

// Pre-select medicine if passed via query param
$preselectedMedicineId = isset($_GET['medicine_id']) ? (int) $_GET['medicine_id'] : null;
$preselectedMedicine = $preselectedMedicineId ? $medicineObj->getById($preselectedMedicineId) : null;

// Handle form submission
$successMsg = '';
$errorMsg = '';
$adjustmentResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid security token. Please try again.';
    } else {
        $medicineId = (int) ($_POST['medicine_id'] ?? 0);
        $adjustmentType = trim($_POST['adjustment_type'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $reference = trim($_POST['reference'] ?? '');

        // Validation
        if ($medicineId <= 0) {
            $errorMsg = 'Please select a medicine.';
        } elseif (!in_array($adjustmentType, ['in', 'out', 'adjustment'], true)) {
            $errorMsg = 'Invalid adjustment type.';
        } elseif ($quantity <= 0) {
            $errorMsg = 'Quantity must be greater than 0.';
        } else {
            $medicine = $medicineObj->getById($medicineId);
            if (!$medicine) {
                $errorMsg = 'Medicine not found.';
            } else {
                $qtyBefore = (int) $medicine['quantity'];

                // Calculate after quantity
                if ($adjustmentType === 'in') {
                    $qtyAfter = $qtyBefore + $quantity;
                } elseif ($adjustmentType === 'out') {
                    $qtyAfter = $qtyBefore - $quantity;
                    if ($qtyAfter < 0) {
                        $qtyAfter = 0;
                    }
                } else {
                    // Adjustment: set to this value (treat quantity as the NEW total)
                    $qtyAfter = $quantity;
                }

                // Update medicine quantity
                $updateResult = $medicineObj->update($medicineId, ['quantity' => $qtyAfter]);

                if ($updateResult) {
                    // Log to inventory_logs
                    logInventory(
                        $medicineId,
                        $adjustmentType,
                        $qtyBefore,
                        $qtyAfter,
                        $reference ?: 'manual_adjustment',
                        null,
                        $reason ?: ucfirst($adjustmentType) . ' adjustment of ' . $quantity . ' units'
                    );

                    $adjustmentResult = [
                        'medicine' => $medicine['name'],
                        'type' => $adjustmentType,
                        'before' => $qtyBefore,
                        'after' => $qtyAfter,
                        'change' => $qtyAfter - $qtyBefore,
                    ];

                    $successMsg = 'Stock adjusted successfully for ' . sanitize($medicine['name']) . '.';
                    
                    // Refresh preselected medicine for display
                    $preselectedMedicine = $medicineObj->getById($medicineId);
                } else {
                    $errorMsg = 'Failed to update stock. Please try again.';
                }
            }
        }
    }

    // Regenerate CSRF token after form submission
    regenerateCsrfToken();
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Adjust Stock - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .adjust-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            align-items: start;
        }

        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
        }
        .form-card:hover {
            box-shadow: var(--shadow-md);
        }
        .form-card .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
        }
        .form-card .card-header h4 {
            font-size: 1.02rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-card .card-header h4 i {
            color: var(--primary);
            font-size: 1.15rem;
        }
        .form-card .card-body {
            padding: 24px 22px;
        }

        /* Search dropdown */
        .medicine-search-wrapper {
            position: relative;
        }
        .medicine-search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.92rem;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: var(--transition);
            outline: none;
        }
        .medicine-search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 179, 66, 0.12);
        }
        .medicine-search-input::placeholder {
            color: var(--text-muted);
        }
        .medicine-search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
        }
        .medicine-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }
        .medicine-dropdown.show {
            display: block;
        }
        .medicine-dropdown-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }
        .medicine-dropdown-item:last-child {
            border-bottom: none;
        }
        .medicine-dropdown-item:hover {
            background: var(--primary-50);
        }
        .medicine-dropdown-item.selected {
            background: var(--primary-100);
        }
        .medicine-dropdown-item .med-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .medicine-dropdown-item .med-detail {
            font-size: 0.74rem;
            color: var(--text-muted);
        }
        .medicine-dropdown-item .med-stock {
            font-size: 0.82rem;
            font-weight: 700;
            min-width: 50px;
            text-align: right;
        }
        .medicine-dropdown-item .med-stock.low { color: var(--danger); }
        .medicine-dropdown-item .med-stock.ok { color: var(--success); }

        /* Selected medicine card */
        .selected-medicine-card {
            display: none;
            background: var(--primary-50);
            border: 1.5px solid var(--primary-200);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-top: 12px;
        }
        .selected-medicine-card.show {
            display: block;
        }
        .selected-medicine-card .sm-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .selected-medicine-card .sm-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        .selected-medicine-card .sm-remove {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: rgba(231,76,60,0.1);
            color: var(--danger);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        .selected-medicine-card .sm-remove:hover {
            background: var(--danger);
            color: #fff;
        }
        .selected-medicine-card .sm-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .sm-detail-item {
            text-align: center;
        }
        .sm-detail-item .sm-detail-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.03em;
        }
        .sm-detail-item .sm-detail-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: 2px;
        }

        /* Adjustment type radio buttons */
        .adjustment-type-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .adjustment-type-option {
            position: relative;
        }
        .adjustment-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .adjustment-type-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 16px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        .adjustment-type-option label:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }
        .adjustment-type-option input[type="radio"]:checked + label {
            border-color: var(--primary);
            background: var(--primary-50);
            box-shadow: 0 0 0 3px rgba(124,179,66,0.15);
        }
        .adjustment-type-option label .type-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .type-in .type-icon { background: rgba(39,174,96,0.12); color: var(--success); }
        .type-out .type-icon { background: rgba(243,156,18,0.12); color: var(--warning); }
        .type-adjustment .type-icon { background: rgba(52,152,219,0.12); color: var(--info); }

        .adjustment-type-option input[type="radio"]:checked + label .type-icon {
            background: var(--primary);
            color: #fff;
        }
        .adjustment-type-option label .type-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .adjustment-type-option label .type-desc {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* Preview card */
        .preview-card {
            position: sticky;
            top: 24px;
        }
        .preview-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding: 28px 20px;
        }
        .preview-qty {
            text-align: center;
        }
        .preview-qty .pq-label {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .preview-qty .pq-value {
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
        }
        .preview-qty .pq-value.before { color: var(--text-secondary); }
        .preview-qty .pq-value.after { color: var(--primary); }
        .preview-qty .pq-value.after.negative { color: var(--danger); }

        .preview-arrow {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }
        .preview-arrow i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        .preview-arrow .change-badge {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 10px;
        }
        .change-badge.positive { background: rgba(39,174,96,0.12); color: var(--success); }
        .change-badge.negative { background: rgba(231,76,60,0.12); color: var(--danger); }
        .change-badge.neutral  { background: rgba(52,152,219,0.12); color: var(--info); }

        /* Alert messages */
        .alert-card {
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.88rem;
        }
        .alert-card.alert-success-card {
            background: rgba(39,174,96,0.08);
            border: 1px solid rgba(39,174,96,0.2);
            color: #27ae60;
        }
        .alert-card.alert-error-card {
            background: rgba(231,76,60,0.08);
            border: 1px solid rgba(231,76,60,0.2);
            color: #e74c3c;
        }
        .alert-card i {
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Footer */
        .app-footer {
            margin-top: 32px;
            padding: 18px 0;
            border-top: 1px solid var(--border-color);
        }
        .app-footer .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .app-footer .footer-content p { margin: 0; }
        .app-footer .footer-version { color: var(--text-muted); font-size: 0.76rem; }

        @media (max-width: 992px) {
            .adjust-layout {
                grid-template-columns: 1fr;
            }
            .preview-card {
                position: static;
            }
        }
        @media (max-width: 576px) {
            .adjustment-type-group {
                grid-template-columns: 1fr;
            }
            .selected-medicine-card .sm-details {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../../includes/templates/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">

        <!-- Header -->
        <?php include __DIR__ . '/../../includes/templates/header.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="ri-add-circle-line" style="color: var(--primary); margin-right: 8px;"></i>Adjust Stock</h2>
                <div class="breadcrumb">
                    <a href="/modules/dashboard/"><i class="ri-home-4-line"></i> Home</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <a href="/modules/inventory/">Inventory</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>Adjust Stock</span>
                </div>
            </div>
            <div>
                <a href="/modules/inventory/" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Back to Inventory
                </a>
            </div>
        </div>

        <!-- Success / Error Messages -->
        <?php if ($successMsg): ?>
        <div class="alert-card alert-success-card">
            <i class="ri-checkbox-circle-line"></i>
            <div><?= $successMsg ?></div>
        </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
        <div class="alert-card alert-error-card">
            <i class="ri-error-warning-line"></i>
            <div><?= sanitize($errorMsg) ?></div>
        </div>
        <?php endif; ?>

        <!-- Adjustment Form + Preview -->
        <div class="adjust-layout">
            <!-- Form Card -->
            <div class="form-card">
                <div class="card-header">
                    <h4><i class="ri-edit-line"></i> Stock Adjustment Form</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="adjustForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="medicine_id" id="medicineIdInput" value="<?= $preselectedMedicineId ?? '' ?>">

                        <!-- Select Medicine -->
                        <div class="form-group">
                            <label class="form-label">Select Medicine <span style="color:var(--danger);">*</span></label>
                            <div class="medicine-search-wrapper">
                                <i class="ri-search-line medicine-search-icon"></i>
                                <input type="text"
                                       class="medicine-search-input"
                                       id="medicineSearch"
                                       placeholder="Search by name, generic name, or batch number..."
                                       autocomplete="off"
                                       value="<?= $preselectedMedicine ? sanitize($preselectedMedicine['name']) : '' ?>">
                                <div class="medicine-dropdown" id="medicineDropdown">
                                    <?php foreach ($allMedicines as $med): ?>
                                    <div class="medicine-dropdown-item <?= $preselectedMedicineId === (int)$med['id'] ? 'selected' : '' ?>"
                                         data-id="<?= $med['id'] ?>"
                                         data-name="<?= sanitize($med['name']) ?>"
                                         data-qty="<?= $med['quantity'] ?>"
                                         data-min="<?= $med['min_stock_level'] ?>"
                                         data-category="<?= sanitize($med['category_name'] ?? 'Uncategorized') ?>"
                                         data-batch="<?= sanitize($med['batch_number'] ?? 'N/A') ?>"
                                         onclick="selectMedicine(this)">
                                        <div>
                                            <div class="med-name"><?= sanitize($med['name']) ?></div>
                                            <div class="med-detail"><?= sanitize($med['category_name'] ?? 'Uncategorized') ?> &middot; Batch: <?= sanitize($med['batch_number'] ?? 'N/A') ?></div>
                                        </div>
                                        <div class="med-stock <?= (int)$med['quantity'] <= (int)$med['min_stock_level'] ? 'low' : 'ok' ?>">
                                            <?= $med['quantity'] ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Selected Medicine Info Card -->
                            <div class="selected-medicine-card <?= $preselectedMedicine ? 'show' : '' ?>" id="selectedMedicineCard">
                                <div class="sm-header">
                                    <span class="sm-name" id="smName"><?= $preselectedMedicine ? sanitize($preselectedMedicine['name']) : '' ?></span>
                                    <button type="button" class="sm-remove" onclick="clearMedicineSelection()" title="Remove selection">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="sm-details">
                                    <div class="sm-detail-item">
                                        <div class="sm-detail-label">Current Stock</div>
                                        <div class="sm-detail-value" id="smCurrentStock" style="color: var(--primary);"><?= $preselectedMedicine ? (int)$preselectedMedicine['quantity'] : '—' ?></div>
                                    </div>
                                    <div class="sm-detail-item">
                                        <div class="sm-detail-label">Min Level</div>
                                        <div class="sm-detail-value" id="smMinLevel"><?= $preselectedMedicine ? (int)$preselectedMedicine['min_stock_level'] : '—' ?></div>
                                    </div>
                                    <div class="sm-detail-item">
                                        <div class="sm-detail-label">Batch #</div>
                                        <div class="sm-detail-value" id="smBatch" style="font-size:0.9rem;"><?= $preselectedMedicine ? sanitize($preselectedMedicine['batch_number'] ?? 'N/A') : '—' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Adjustment Type -->
                        <div class="form-group">
                            <label class="form-label">Adjustment Type <span style="color:var(--danger);">*</span></label>
                            <div class="adjustment-type-group">
                                <div class="adjustment-type-option type-in">
                                    <input type="radio" name="adjustment_type" id="typeIn" value="in" checked>
                                    <label for="typeIn">
                                        <div class="type-icon"><i class="ri-add-line"></i></div>
                                        <div class="type-label">Stock In</div>
                                        <div class="type-desc">Add to inventory</div>
                                    </label>
                                </div>
                                <div class="adjustment-type-option type-out">
                                    <input type="radio" name="adjustment_type" id="typeOut" value="out">
                                    <label for="typeOut">
                                        <div class="type-icon"><i class="ri-subtract-line"></i></div>
                                        <div class="type-label">Stock Out</div>
                                        <div class="type-desc">Remove from inventory</div>
                                    </label>
                                </div>
                                <div class="adjustment-type-option type-adjustment">
                                    <input type="radio" name="adjustment_type" id="typeAdj" value="adjustment">
                                    <label for="typeAdj">
                                        <div class="type-icon"><i class="ri-swap-line"></i></div>
                                        <div class="type-label">Adjustment</div>
                                        <div class="type-desc">Set exact quantity</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div class="form-group">
                            <label class="form-label" for="quantityInput">
                                Quantity <span style="color:var(--danger);">*</span>
                                <span id="qtyHint" style="font-weight:400; color:var(--text-muted); font-size:0.78rem; margin-left:8px;">
                                    (Amount to add / remove)
                                </span>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="quantityInput"
                                   name="quantity"
                                   min="1"
                                   placeholder="Enter quantity"
                                   required
                                   oninput="updatePreview()">
                        </div>

                        <!-- Reason / Notes -->
                        <div class="form-group">
                            <label class="form-label" for="reasonInput">Reason / Notes</label>
                            <textarea class="form-control"
                                      id="reasonInput"
                                      name="reason"
                                      rows="3"
                                      placeholder="E.g., Restocked from supplier, Damaged items removed, Stock count correction..."></textarea>
                        </div>

                        <!-- Reference -->
                        <div class="form-group">
                            <label class="form-label" for="referenceInput">
                                Reference <span style="font-weight:400; color:var(--text-muted); font-size:0.78rem;">(Optional - PO number, etc.)</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="referenceInput"
                                   name="reference"
                                   placeholder="E.g., PO-2024-001, GRN-123">
                        </div>

                        <!-- Submit -->
                        <div style="display:flex; gap:12px; margin-top:8px;">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex:1;">
                                <i class="ri-check-line"></i> Apply Adjustment
                            </button>
                            <button type="reset" class="btn btn-outline-secondary btn-lg" onclick="resetForm()">
                                <i class="ri-refresh-line"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Preview Card -->
            <div class="preview-card">
                <div class="form-card">
                    <div class="card-header">
                        <h4><i class="ri-eye-line"></i> Adjustment Preview</h4>
                    </div>
                    <div class="card-body">
                        <!-- Before → After Visual -->
                        <div class="preview-visual">
                            <div class="preview-qty">
                                <div class="pq-label">Before</div>
                                <div class="pq-value before" id="previewBefore">—</div>
                            </div>
                            <div class="preview-arrow">
                                <i class="ri-arrow-right-line"></i>
                                <span class="change-badge neutral" id="previewChangeBadge">0</span>
                            </div>
                            <div class="preview-qty">
                                <div class="pq-label">After</div>
                                <div class="pq-value after" id="previewAfter">—</div>
                            </div>
                        </div>

                        <!-- Summary Details -->
                        <div style="border-top:1px solid var(--border-color); padding-top:16px; margin-top:4px;">
                            <div style="display:flex; justify-content:space-between; padding:6px 0; font-size:0.88rem;">
                                <span style="color:var(--text-muted);">Medicine</span>
                                <span style="font-weight:600;" id="previewMedicine">—</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0; font-size:0.88rem;">
                                <span style="color:var(--text-muted);">Adjustment Type</span>
                                <span style="font-weight:600;" id="previewType">Stock In</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0; font-size:0.88rem;">
                                <span style="color:var(--text-muted);">Change</span>
                                <span style="font-weight:700;" id="previewChange">+0</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; padding:6px 0; font-size:0.88rem; border-top:1px solid var(--border-color); margin-top:8px; padding-top:12px;">
                                <span style="color:var(--text-muted);">Resulting Stock</span>
                                <span style="font-weight:700; font-size:1.1rem; color:var(--primary);" id="previewResult">—</span>
                            </div>
                        </div>

                        <!-- Warning -->
                        <div id="previewWarning" style="display:none; margin-top:16px; padding:10px 14px; border-radius:var(--radius-sm); background:rgba(243,156,18,0.08); border:1px solid rgba(243,156,18,0.2); font-size:0.82rem; color:var(--warning); display:flex; align-items:center; gap:8px;">
                            <i class="ri-alert-line"></i>
                            <span id="previewWarningText"></span>
                        </div>
                    </div>
                </div>

                <!-- Recent Adjustments Link -->
                <div class="form-card" style="margin-top:16px;">
                    <div class="card-body" style="padding:16px 20px;">
                        <a href="/modules/inventory/logs.php" style="display:flex; align-items:center; gap:10px; font-size:0.88rem; font-weight:600; color:var(--primary); text-decoration:none;">
                            <i class="ri-file-list-3-line"></i> View Movement Logs
                            <i class="ri-arrow-right-s-line" style="margin-left:auto;"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<script>
// ─── State ─────────────────────────────────────────────────
let currentStock = 0;
let minLevel = 0;

<?php if ($preselectedMedicine): ?>
currentStock = <?= (int) $preselectedMedicine['quantity'] ?>;
minLevel = <?= (int) $preselectedMedicine['min_stock_level'] ?>;
<?php endif; ?>

// ─── Medicine Search Dropdown ──────────────────────────────
const searchInput = document.getElementById('medicineSearch');
const dropdown = document.getElementById('medicineDropdown');
const allItems = dropdown.querySelectorAll('.medicine-dropdown-item');

searchInput.addEventListener('focus', () => {
    dropdown.classList.add('show');
    filterDropdown(searchInput.value);
});

searchInput.addEventListener('input', () => {
    dropdown.classList.add('show');
    filterDropdown(searchInput.value);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.medicine-search-wrapper')) {
        dropdown.classList.remove('show');
    }
});

function filterDropdown(query) {
    const q = query.toLowerCase();
    allItems.forEach(item => {
        const name = item.dataset.name.toLowerCase();
        const batch = item.dataset.batch.toLowerCase();
        const category = item.dataset.category.toLowerCase();
        if (name.includes(q) || batch.includes(q) || category.includes(q)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// ─── Select Medicine ───────────────────────────────────────
function selectMedicine(el) {
    const id = el.dataset.id;
    const name = el.dataset.name;
    const qty = parseInt(el.dataset.qty);
    const min = parseInt(el.dataset.min);
    const batch = el.dataset.batch;

    document.getElementById('medicineIdInput').value = id;
    searchInput.value = name;
    currentStock = qty;
    minLevel = min;

    // Update selected medicine card
    document.getElementById('smName').textContent = name;
    document.getElementById('smCurrentStock').textContent = qty;
    document.getElementById('smMinLevel').textContent = min;
    document.getElementById('smBatch').textContent = batch;
    document.getElementById('selectedMedicineCard').classList.add('show');

    // Mark selected
    allItems.forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');

    dropdown.classList.remove('show');
    updatePreview();
}

function clearMedicineSelection() {
    document.getElementById('medicineIdInput').value = '';
    searchInput.value = '';
    currentStock = 0;
    minLevel = 0;
    document.getElementById('selectedMedicineCard').classList.remove('show');
    allItems.forEach(i => i.classList.remove('selected'));
    updatePreview();
}

// ─── Adjustment Type Change ────────────────────────────────
document.querySelectorAll('input[name="adjustment_type"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const type = radio.value;
        const hint = document.getElementById('qtyHint');
        if (type === 'in') {
            hint.textContent = '(Amount to add)';
        } else if (type === 'out') {
            hint.textContent = '(Amount to remove)';
        } else {
            hint.textContent = '(New total quantity)';
        }
        updatePreview();
    });
});

// ─── Preview Update ────────────────────────────────────────
function updatePreview() {
    const type = document.querySelector('input[name="adjustment_type"]:checked')?.value || 'in';
    const qty = parseInt(document.getElementById('quantityInput').value) || 0;
    const medicineId = document.getElementById('medicineIdInput').value;
    const medicineName = searchInput.value || '—';

    let afterQty = currentStock;
    let change = 0;

    if (type === 'in') {
        afterQty = currentStock + qty;
        change = qty;
    } else if (type === 'out') {
        afterQty = Math.max(0, currentStock - qty);
        change = -Math.min(qty, currentStock);
    } else {
        // Adjustment = set to exact value
        afterQty = qty;
        change = qty - currentStock;
    }

    // Before / After
    document.getElementById('previewBefore').textContent = currentStock || '—';
    document.getElementById('previewAfter').textContent = medicineId ? afterQty : '—';
    document.getElementById('previewAfter').className = 'pq-value after' + (afterQty <= minLevel ? ' negative' : '');

    // Change badge
    const changeBadge = document.getElementById('previewChangeBadge');
    changeBadge.textContent = (change >= 0 ? '+' : '') + change;
    changeBadge.className = 'change-badge ' + (change > 0 ? 'positive' : (change < 0 ? 'negative' : 'neutral'));

    // Summary details
    document.getElementById('previewMedicine').textContent = medicineName;
    const typeLabels = { in: 'Stock In', out: 'Stock Out', adjustment: 'Adjustment' };
    document.getElementById('previewType').textContent = typeLabels[type] || type;
    document.getElementById('previewChange').textContent = (change >= 0 ? '+' : '') + change;
    document.getElementById('previewChange').style.color = change > 0 ? 'var(--success)' : (change < 0 ? 'var(--danger)' : 'var(--info)');
    document.getElementById('previewResult').textContent = medicineId ? afterQty : '—';
    document.getElementById('previewResult').style.color = afterQty <= minLevel ? 'var(--danger)' : 'var(--primary)';

    // Warning
    const warning = document.getElementById('previewWarning');
    const warningText = document.getElementById('previewWarningText');
    if (medicineId && afterQty <= minLevel && afterQty > 0) {
        warning.style.display = 'flex';
        warningText.textContent = `Resulting stock will be at or below the minimum level (${minLevel}).`;
    } else if (medicineId && afterQty === 0) {
        warning.style.display = 'flex';
        warning.style.background = 'rgba(231,76,60,0.08)';
        warning.style.borderColor = 'rgba(231,76,60,0.2)';
        warning.style.color = 'var(--danger)';
        warningText.textContent = 'This will result in zero stock for this medicine!';
    } else {
        warning.style.display = 'none';
    }
}

// ─── Form Reset ────────────────────────────────────────────
function resetForm() {
    clearMedicineSelection();
    document.getElementById('quantityInput').value = '';
    document.getElementById('reasonInput').value = '';
    document.getElementById('referenceInput').value = '';
    document.getElementById('typeIn').checked = true;
    updatePreview();
}

// ─── Form Validation ───────────────────────────────────────
document.getElementById('adjustForm').addEventListener('submit', function(e) {
    const medicineId = document.getElementById('medicineIdInput').value;
    const qty = parseInt(document.getElementById('quantityInput').value);

    if (!medicineId) {
        e.preventDefault();
        alert('Please select a medicine.');
        return;
    }
    if (!qty || qty <= 0) {
        e.preventDefault();
        alert('Please enter a valid quantity greater than 0.');
        return;
    }
});

// ─── Initialize Preview ────────────────────────────────────
updatePreview();
</script>
</body>
</html>
