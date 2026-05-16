<?php
/**
 * MedWell Pharmacy - Medicines Listing Page
 *
 * Premium medicines management page with DataTable, filters,
 * status badges, and expiry color coding.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'medicines';
$pageTitle = 'Medicines';

// Fetch data
$medicineObj = new Medicine();
$categories = $medicineObj->getCategories();
$lowStockMedicines = $medicineObj->getLowStock();
$lowStockCount = count($lowStockMedicines);

// Build filters from GET params
$filters = ['is_active' => 1];
if (!empty($_GET['category_id'])) {
    $filters['category_id'] = (int) $_GET['category_id'];
}
if (!empty($_GET['stock_status'])) {
    $filters['stock_status'] = $_GET['stock_status'];
}
if (!empty($_GET['expiry_filter'])) {
    $filters['expiry_filter'] = $_GET['expiry_filter'];
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$medicines = $medicineObj->getAll($filters, $search, $limit, $offset);
$totalMedicines = $medicineObj->getTotalCount();

// Flash messages
$flashSuccess = getFlashMessage('medicine_success');
$flashError = getFlashMessage('medicine_error');
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
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ── Medicines Page Styles ── */
        .medicines-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .medicines-header .header-left h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .medicines-header .header-left h2 i {
            color: var(--primary);
            font-size: 1.4rem;
        }
        .medicines-header .header-left .subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .medicines-header .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Filter Bar */
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        .filter-bar .filter-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-bar .filter-group {
            flex: 1;
            min-width: 160px;
        }
        .filter-bar .filter-group label {
            display: block;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }
        .filter-bar .filter-group select,
        .filter-bar .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.86rem;
            color: var(--text-primary);
            background: var(--bg-card);
            transition: var(--transition);
            outline: none;
            font-family: var(--font-sans);
        }
        .filter-bar .filter-group select:focus,
        .filter-bar .filter-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }
        .filter-bar .filter-group .search-input-wrapper {
            position: relative;
        }
        .filter-bar .filter-group .search-input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .filter-bar .filter-group .search-input-wrapper input {
            padding-left: 36px;
        }
        .filter-bar .btn-reset {
            padding: 8px 16px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 0.84rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .filter-bar .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(231, 76, 60, 0.06);
        }

        /* Stats Summary Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        /* Table Card */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .table-card .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .table-card .card-top .bulk-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .table-card .card-top .bulk-actions select {
            padding: 7px 30px 7px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.84rem;
            color: var(--text-secondary);
            background: var(--bg-card);
            cursor: pointer;
            outline: none;
            appearance: none;
            font-family: var(--font-sans);
        }
        .table-card .card-top .record-count {
            font-size: 0.84rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .table-card .card-top .record-count strong {
            color: var(--text-primary);
        }

        /* Data Table Overrides */
        .dataTables_wrapper {
            padding: 0 !important;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 22px !important;
            font-size: 0.84rem;
            color: var(--text-secondary);
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            margin-left: 8px;
            outline: none;
            transition: var(--transition);
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            margin: 0 2px !important;
            border: 1px solid var(--border-color) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-50) !important;
            color: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        table.dataTable tbody td {
            vertical-align: middle;
            padding: 12px 16px !important;
            font-size: 0.86rem;
        }
        table.dataTable thead th {
            background: var(--primary-50) !important;
            color: var(--text-secondary) !important;
            font-weight: 600 !important;
            font-size: 0.78rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 16px !important;
            border-bottom: 2px solid var(--border-color) !important;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .status-badge.badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }
        .status-badge.badge-success::before { background: #27ae60; }
        .status-badge.badge-warning {
            background: rgba(243, 156, 18, 0.1);
            color: #e67e22;
        }
        .status-badge.badge-warning::before { background: #f39c12; }
        .status-badge.badge-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        .status-badge.badge-danger::before { background: #e74c3c; }

        /* Expiry Color Coding */
        .expiry-date {
            font-size: 0.84rem;
            font-weight: 500;
            white-space: nowrap;
        }
        .expiry-date.expired { color: #e74c3c; }
        .expiry-date.expiring-soon { color: #e67e22; }
        .expiry-date.safe { color: #27ae60; }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .action-btn.btn-view:hover {
            border-color: var(--info);
            color: var(--info);
            background: rgba(52, 152, 219, 0.06);
        }
        .action-btn.btn-edit:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .action-btn.btn-delete:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(231, 76, 60, 0.06);
        }

        /* Checkbox */
        .medicine-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        /* Medicine Name Cell */
        .med-name-cell {
            display: flex;
            flex-direction: column;
        }
        .med-name-cell .med-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.88rem;
        }
        .med-name-cell .med-generic {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Price Cell */
        .price-cell {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 0.9rem;
        }

        /* Stock Qty Cell */
        .stock-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stock-cell .stock-bar {
            width: 40px;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
        }
        .stock-cell .stock-bar-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.5s ease;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .filter-bar .filter-row { flex-direction: column; }
            .filter-bar .filter-group { min-width: 100%; }
            .medicines-header { flex-direction: column; align-items: flex-start; }
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
        <div class="medicines-header">
            <div class="header-left">
                <h2><i class="ri-medicine-bottle-line"></i> Medicines</h2>
                <div class="subtitle">Manage your pharmacy inventory</div>
            </div>
            <div class="header-actions">
                <a href="/modules/medicines/categories.php" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-folder-line"></i> Categories
                </a>
                <button class="btn btn-outline-secondary btn-sm" onclick="exportMedicines()" title="Export">
                    <i class="ri-download-line"></i> Export
                </button>
                <a href="/modules/medicines/add.php" class="btn btn-primary btn-sm">
                    <i class="ri-add-line"></i> Add Medicine
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

        <!-- Quick Stats -->
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
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">In Stock</div>
                    <div class="stat-value"><?= number_format($totalMedicines - $lowStockCount) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift" style="--accent-bar: var(--warning);">
                <div class="stat-icon icon-warning">
                    <i class="ri-alarm-warning-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Low Stock</div>
                    <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
                        <?= number_format($lowStockCount) ?>
                    </div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-info">
                    <i class="ri-archive-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Categories</div>
                    <div class="stat-value"><?= number_format(count($categories)) ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form class="filter-bar" method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label><i class="ri-folder-line"></i> Category</label>
                    <select name="category_id" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="ri-pie-chart-line"></i> Stock Status</label>
                    <select name="stock_status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="in_stock" <?= ($_GET['stock_status'] ?? '') === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                        <option value="low_stock" <?= ($_GET['stock_status'] ?? '') === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out_of_stock" <?= ($_GET['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="ri-calendar-line"></i> Expiry</label>
                    <select name="expiry_filter" onchange="this.form.submit()">
                        <option value="">All Expiry</option>
                        <option value="expired" <?= ($_GET['expiry_filter'] ?? '') === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="expiring_30" <?= ($_GET['expiry_filter'] ?? '') === 'expiring_30' ? 'selected' : '' ?>>Expiring in 30 days</option>
                        <option value="expiring_90" <?= ($_GET['expiry_filter'] ?? '') === 'expiring_90' ? 'selected' : '' ?>>Expiring in 90 days</option>
                        <option value="safe" <?= ($_GET['expiry_filter'] ?? '') === 'safe' ? 'selected' : '' ?>>Safe (> 90 days)</option>
                    </select>
                </div>
                <div class="filter-group" style="flex: 1.5;">
                    <label><i class="ri-search-line"></i> Search</label>
                    <div class="search-input-wrapper">
                        <i class="ri-search-line"></i>
                        <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search by name, generic name, or batch...">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">
                    <i class="ri-search-line"></i> Search
                </button>
                <a href="/modules/medicines/" class="btn-reset">
                    <i class="ri-refresh-line"></i> Reset
                </a>
            </div>
        </form>

        <!-- Data Table Card -->
        <div class="table-card">
            <div class="card-top">
                <div class="bulk-actions">
                    <select id="bulkAction" class="form-select" style="width: auto; min-width: 160px; padding: 7px 32px 7px 12px; font-size: 0.84rem;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                        <option value="export">Export Selected</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" onclick="applyBulkAction()">
                        <i class="ri-check-line"></i> Apply
                    </button>
                </div>
                <div class="record-count">
                    Showing <strong><?= count($medicines) ?></strong> of <strong><?= number_format($totalMedicines) ?></strong> medicines
                </div>
            </div>

            <div class="table-container">
                <table id="medicinesTable" class="data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 36px;"><input type="checkbox" class="medicine-checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Batch #</th>
                            <th>Price</th>
                            <th>Stock Qty</th>
                            <th>Min Level</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($medicines)): ?>
                            <?php foreach ($medicines as $med):
                                $qty = (int) ($med['quantity'] ?? 0);
                                $minLevel = (int) ($med['min_stock_level'] ?? 10);

                                // Stock status
                                if ($qty === 0) {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Out of Stock';
                                } elseif ($qty <= $minLevel) {
                                    $statusClass = 'badge-warning';
                                    $statusText = 'Low Stock';
                                } else {
                                    $statusClass = 'badge-success';
                                    $statusText = 'In Stock';
                                }

                                // Expiry color coding
                                $expiryClass = '';
                                $expiryDisplay = '';
                                if (!empty($med['expiry_date']) && $med['expiry_date'] !== '0000-00-00') {
                                    $daysLeft = calculateExpiry($med['expiry_date']);
                                    if ($daysLeft < 0) {
                                        $expiryClass = 'expired';
                                    } elseif ($daysLeft <= 30) {
                                        $expiryClass = 'expiring-soon';
                                    } elseif ($daysLeft > 90) {
                                        $expiryClass = 'safe';
                                    }
                                    $expiryDisplay = formatDate($med['expiry_date']);
                                } else {
                                    $expiryDisplay = '<span style="color: var(--text-muted);">N/A</span>';
                                }

                                // Stock bar
                                $stockPercent = $minLevel > 0 ? min(($qty / ($minLevel * 3)) * 100, 100) : 100;
                                $barColor = $qty === 0 ? 'var(--danger)' : ($qty <= $minLevel ? 'var(--warning)' : 'var(--success)');
                            ?>
                            <tr>
                                <td><input type="checkbox" class="medicine-checkbox" value="<?= $med['id'] ?>"></td>
                                <td style="font-weight: 600; color: var(--text-muted); font-size: 0.82rem;"><?= $med['id'] ?></td>
                                <td>
                                    <div class="med-name-cell">
                                        <span class="med-name"><?= sanitize($med['name']) ?></span>
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
                                <td style="font-family: var(--font-mono, monospace); font-size: 0.82rem; color: var(--text-secondary);">
                                    <?= sanitize($med['batch_number'] ?? 'N/A') ?>
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
                                <td style="color: var(--text-muted); font-size: 0.84rem;"><?= number_format($minLevel) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td>
                                    <?php if (!empty($expiryClass)): ?>
                                    <span class="expiry-date <?= $expiryClass ?>"><?= $expiryDisplay ?></span>
                                    <?php else: ?>
                                    <?= $expiryDisplay ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="/modules/medicines/view.php?id=<?= $med['id'] ?>" class="action-btn btn-view" title="View">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="/modules/medicines/edit.php?id=<?= $med['id'] ?>" class="action-btn btn-edit" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <button class="action-btn btn-delete" title="Delete" onclick="deleteMedicine(<?= $med['id'] ?>, '<?= htmlspecialchars($med['name'], ENT_QUOTES) ?>')">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 48px 20px;">
                                    <div style="color: var(--text-muted);">
                                        <i class="ri-medicine-bottle-line" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                                        <p style="font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">No medicines found</p>
                                        <p style="font-size: 0.86rem;">Try adjusting your filters or add a new medicine.</p>
                                        <a href="/modules/medicines/add.php" class="btn btn-primary btn-sm" style="margin-top: 16px;">
                                            <i class="ri-add-line"></i> Add Medicine
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><i class="ri-delete-bin-line" style="color: var(--danger);"></i> Delete Medicine</h3>
            <button class="modal-close" onclick="closeDeleteModal()"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); font-size: 0.92rem;">
                Are you sure you want to delete <strong id="deleteMedName" style="color: var(--text-primary);"></strong>?
            </p>
            <p style="color: var(--text-muted); font-size: 0.84rem; margin-top: 8px;">
                This action will deactivate the medicine. You can restore it later from settings.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary btn-sm" onclick="closeDeleteModal()">Cancel</button>
            <form id="deleteForm" method="POST" action="/modules/medicines/delete.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" id="deleteMedId" value="">
                <button type="submit" class="btn btn-danger btn-sm"><i class="ri-delete-bin-line"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
// Initialize DataTable
$(document).ready(function() {
    $('#medicinesTable').DataTable({
        pageLength: 25,
        order: [[2, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 10] },
            { searchable: false, targets: [0] }
        ],
        language: {
            search: '<i class="ri-search-line"></i>',
            searchPlaceholder: 'Quick search...',
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

// Select All Checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.medicine-checkbox').forEach(cb => cb.checked = this.checked);
});

// Delete Medicine
function deleteMedicine(id, name) {
    document.getElementById('deleteMedId').value = id;
    document.getElementById('deleteMedName').textContent = name;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Bulk Actions
function applyBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const selected = Array.from(document.querySelectorAll('.medicine-checkbox:checked:not(#selectAll)')).map(cb => cb.value);

    if (!action) {
        alert('Please select a bulk action.');
        return;
    }
    if (selected.length === 0) {
        alert('Please select at least one medicine.');
        return;
    }

    if (action === 'delete') {
        if (!confirm(`Are you sure you want to delete ${selected.length} medicine(s)?`)) return;
        // Process via form or AJAX
        selected.forEach(id => {
            fetch('/modules/medicines/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&csrf_token=<?= generateCsrfToken() ?>`
            });
        });
        location.reload();
    } else if (action === 'export') {
        exportMedicines();
    }
}

// Export
function exportMedicines() {
    window.location.href = '/modules/medicines/?export=csv';
}
</script>
</body>
</html>
