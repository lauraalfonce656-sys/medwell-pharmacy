<?php
/**
 * MedWell Pharmacy - Supplier Detail / View Page
 *
 * Premium detail view with supplier profile card, medicines
 * supplied DataTable, stats cards, and quick actions.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'suppliers';
$pageTitle = 'Supplier Details';

$supplierObj = new Supplier();

// Load supplier by ID
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('supplier_error', 'Invalid supplier ID.', 'error');
    redirect('/modules/suppliers/');
}

$supplier = $supplierObj->getById($id);
if (!$supplier) {
    flashMessage('supplier_error', 'Supplier not found.', 'error');
    redirect('/modules/suppliers/');
}

// Fetch medicines supplied by this supplier
$medicines = $supplierObj->getSupplierMedicines($id);

// Calculate derived data
$totalMedicines = count($medicines);
$activeMedicines = 0;
$inactiveMedicines = 0;
$totalStockValue = 0.0;

foreach ($medicines as $med) {
    if ((int) ($med['is_active'] ?? 0)) {
        $activeMedicines++;
    } else {
        $inactiveMedicines++;
    }
    $totalStockValue += (int) ($med['quantity'] ?? 0) * (float) ($med['price'] ?? 0);
}

$isActive = (int) ($supplier['is_active'] ?? 1);

// Flash messages
$flashSuccess = getFlashMessage('supplier_success');
$flashError = getFlashMessage('supplier_error');
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
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        /* Supplier Title Section */
        .supplier-title-section {
            display: flex; align-items: flex-start; gap: 20px;
            margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);
        }
        .supplier-title-icon {
            width: 64px; height: 64px; border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; flex-shrink: 0;
        }
        .supplier-title-info { flex: 1; }
        .supplier-title-info h3 { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
        .supplier-title-info .supplier-contact-person {
            font-size: 0.92rem; color: var(--text-secondary);
        }
        .supplier-title-info .supplier-meta {
            display: flex; gap: 16px; margin-top: 10px; flex-wrap: wrap;
        }
        .supplier-title-info .supplier-meta span {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.82rem; color: var(--text-muted); font-weight: 500;
        }
        .supplier-title-info .supplier-meta span i { font-size: 0.9rem; color: var(--primary); }
        .supplier-title-badges { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }

        /* Status Badges */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; letter-spacing: 0.02em; white-space: nowrap;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .status-badge.badge-success { background: rgba(39,174,96,0.1); color: #27ae60; }
        .status-badge.badge-success::before { background: #27ae60; }
        .status-badge.badge-danger { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .status-badge.badge-danger::before { background: #e74c3c; }
        .status-badge.badge-warning { background: rgba(243,156,18,0.1); color: #e67e22; }
        .status-badge.badge-warning::before { background: #f39c12; }
        .status-badge.badge-primary { background: var(--primary-100); color: var(--primary-dark); }
        .status-badge.badge-primary::before { background: var(--primary); }

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
        .detail-item .detail-value.muted { color: var(--text-muted); font-weight: 400; }
        .detail-item .detail-value a {
            color: var(--primary); text-decoration: none;
        }
        .detail-item .detail-value a:hover {
            color: var(--primary-dark); text-decoration: underline;
        }

        /* Stats Row */
        .stats-row {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 20px;
        }

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

        /* Data Table in Section */
        .section-card .table-container { overflow-x: auto; }
        .section-card .data-table { margin-bottom: 0; }
        .section-card .data-table thead th {
            background: var(--primary-50) !important;
            color: var(--text-secondary) !important;
            font-weight: 600 !important;
            font-size: 0.78rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 16px !important;
            border-bottom: 2px solid var(--border-color) !important;
        }
        .section-card .data-table tbody td {
            padding: 12px 16px !important;
            font-size: 0.86rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }
        .section-card .data-table tbody tr:last-child td { border-bottom: none; }

        /* Medicine Name Cell */
        .med-name-cell {
            display: flex; flex-direction: column;
        }
        .med-name-cell .med-name {
            font-weight: 600; color: var(--text-primary); font-size: 0.88rem;
        }
        .med-name-cell .med-generic {
            font-size: 0.76rem; color: var(--text-muted); margin-top: 2px;
        }

        /* Price Cell */
        .price-cell {
            font-weight: 700; color: var(--primary-dark); font-size: 0.9rem;
        }

        /* Stock Cell */
        .stock-cell {
            display: flex; align-items: center; gap: 8px;
        }
        .stock-cell .stock-bar {
            width: 40px; height: 4px; background: var(--border-color);
            border-radius: 2px; overflow: hidden;
        }
        .stock-cell .stock-bar-fill {
            height: 100%; border-radius: 2px; transition: width 0.5s ease;
        }

        /* Empty State */
        .empty-state {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 32px 20px; color: var(--text-muted); text-align: center;
        }
        .empty-state i { font-size: 2rem; margin-bottom: 8px; opacity: 0.3; }
        .empty-state p { font-size: 0.84rem; }

        /* DataTables overrides inside section card */
        .section-card .dataTables_wrapper {
            padding: 0 !important;
        }
        .section-card .dataTables_wrapper .dataTables_length,
        .section-card .dataTables_wrapper .dataTables_filter,
        .section-card .dataTables_wrapper .dataTables_info,
        .section-card .dataTables_wrapper .dataTables_paginate {
            padding: 10px 16px !important;
            font-size: 0.82rem;
        }

        @media (max-width: 1200px) {
            .details-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .details-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr; }
            .supplier-title-section { flex-direction: column; }
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
                <a href="/modules/suppliers/" class="back-btn" title="Back to Suppliers"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <h2>Supplier Details</h2>
                    <div class="subtitle">Viewing supplier #<?= $id ?></div>
                </div>
            </div>
            <div class="header-actions">
                <a href="/modules/medicines/add.php?supplier_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" title="Add Medicine from this Supplier">
                    <i class="ri-medicine-bottle-line"></i> Add Medicine
                </a>
                <a href="/modules/suppliers/edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm">
                    <i class="ri-edit-line"></i> Edit
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
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
                <div class="supplier-title-section">
                    <div class="supplier-title-icon"><i class="ri-truck-line"></i></div>
                    <div class="supplier-title-info">
                        <h3><?= sanitize($supplier['name']) ?></h3>
                        <?php if (!empty($supplier['contact_person'])): ?>
                        <div class="supplier-contact-person">
                            <i class="ri-user-line" style="color: var(--primary);"></i>
                            <?= sanitize($supplier['contact_person']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="supplier-meta">
                            <?php if (!empty($supplier['email'])): ?>
                            <span><i class="ri-mail-line"></i> <a href="mailto:<?= sanitize($supplier['email']) ?>" style="color: var(--primary);"><?= sanitize($supplier['email']) ?></a></span>
                            <?php endif; ?>
                            <?php if (!empty($supplier['phone'])): ?>
                            <span><i class="ri-phone-line"></i> <a href="tel:<?= sanitize($supplier['phone']) ?>" style="color: var(--primary);"><?= sanitize($supplier['phone']) ?></a></span>
                            <?php endif; ?>
                            <?php if (!empty($supplier['city'])): ?>
                            <span><i class="ri-map-pin-line"></i> <?= sanitize($supplier['city']) ?><?php if (!empty($supplier['state'])): ?>, <?= sanitize($supplier['state']) ?><?php endif; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="supplier-title-badges">
                        <span class="status-badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                            <i class="<?= $isActive ? 'ri-checkbox-circle-line' : 'ri-close-circle-line' ?>"></i>
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Supplier Name</div>
                        <div class="detail-value"><?= sanitize($supplier['name']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Contact Person</div>
                        <div class="detail-value"><?= sanitize($supplier['contact_person'] ?? 'Not specified') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">
                            <?php if (!empty($supplier['email'])): ?>
                            <a href="mailto:<?= sanitize($supplier['email']) ?>"><?= sanitize($supplier['email']) ?></a>
                            <?php else: ?>
                            <span class="muted">Not specified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value">
                            <?php if (!empty($supplier['phone'])): ?>
                            <a href="tel:<?= sanitize($supplier['phone']) ?>"><?= sanitize($supplier['phone']) ?></a>
                            <?php else: ?>
                            <span class="muted">Not specified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Address</div>
                        <div class="detail-value"><?= sanitize($supplier['address'] ?? 'Not specified') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">City</div>
                        <div class="detail-value"><?= sanitize($supplier['city'] ?? 'Not specified') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">State / Region</div>
                        <div class="detail-value"><?= sanitize($supplier['state'] ?? 'Not specified') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Zip / Postal Code</div>
                        <div class="detail-value"><?= sanitize($supplier['zip_code'] ?? 'Not specified') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Registered</div>
                        <div class="detail-value muted"><?= formatDateTime($supplier['created_at'] ?? '') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-primary">
                    <i class="ri-medicine-bottle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Medicines</div>
                    <div class="stat-value"><?= number_format($totalMedicines) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-success">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Stock Value</div>
                    <div class="stat-value"><?= formatCurrency($totalStockValue) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-warning">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Active / Inactive</div>
                    <div class="stat-value">
                        <span style="color: var(--success);"><?= $activeMedicines ?></span>
                        <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 400;">/</span>
                        <span style="color: var(--danger);"><?= $inactiveMedicines ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medicines Supplied -->
        <div class="section-card">
            <div class="section-header">
                <h4><i class="ri-medicine-bottle-line"></i> Medicines Supplied</h4>
                <a href="/modules/medicines/add.php?supplier_id=<?= $id ?>" class="btn btn-primary btn-sm" style="font-size: 0.78rem; padding: 5px 12px;">
                    <i class="ri-add-line"></i> Add Medicine
                </a>
            </div>
            <div class="section-body">
                <?php if (!empty($medicines)): ?>
                <div class="table-container">
                    <table id="supplierMedicinesTable" class="data-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicines as $med):
                                $medActive = (int) ($med['is_active'] ?? 1);
                                $qty = (int) ($med['quantity'] ?? 0);
                                $minLevel = (int) ($med['min_stock_level'] ?? 10);

                                if (!$medActive) {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Inactive';
                                } elseif ($qty === 0) {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Out of Stock';
                                } elseif ($qty <= $minLevel) {
                                    $statusClass = 'badge-warning';
                                    $statusText = 'Low Stock';
                                } else {
                                    $statusClass = 'badge-success';
                                    $statusText = 'In Stock';
                                }

                                $stockPercent = $minLevel > 0 ? min(($qty / ($minLevel * 3)) * 100, 100) : 100;
                                $barColor = $qty === 0 ? 'var(--danger)' : ($qty <= $minLevel ? 'var(--warning)' : 'var(--success)');
                            ?>
                            <tr>
                                <td>
                                    <div class="med-name-cell">
                                        <a href="/modules/medicines/view.php?id=<?= $med['id'] ?>" class="med-name" style="color: var(--primary);"><?= sanitize($med['name']) ?></a>
                                        <?php if (!empty($med['generic_name'])): ?>
                                        <span class="med-generic"><?= sanitize($med['generic_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="background: var(--primary-50); color: var(--primary-dark); padding: 3px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;">
                                        <?= sanitize($med['category_name'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td class="price-cell"><?= formatCurrency((float) ($med['price'] ?? 0)) ?></td>
                                <td>
                                    <div class="stock-cell">
                                        <span style="font-weight: 700; min-width: 30px;"><?= number_format($qty) ?></span>
                                        <div class="stock-bar">
                                            <div class="stock-bar-fill" style="width: <?= $stockPercent ?>%; background: <?= $barColor ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="ri-medicine-bottle-line"></i>
                    <p>No medicines supplied by this vendor yet</p>
                    <a href="/modules/medicines/add.php?supplier_id=<?= $id ?>" class="btn btn-primary btn-sm" style="margin-top: 12px;">
                        <i class="ri-add-line"></i> Add First Medicine
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
$(document).ready(function() {
    $('#supplierMedicinesTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [4] }
        ],
        language: {
            search: '<i class="ri-search-line"></i>',
            searchPlaceholder: 'Search medicines...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ medicines',
            paginate: {
                previous: '<i class="ri-arrow-left-s-line"></i>',
                next: '<i class="ri-arrow-right-s-line"></i>'
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });
});
</script>
</body>
</html>
