<?php
/**
 * MedWell Pharmacy - Inventory Dashboard
 *
 * Central inventory management hub with stock overview, status charts,
 * quick filters, and a full DataTable of all medicines with stock levels.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'inventory';

// Fetch data
$medicineObj = new Medicine();
$allMedicines = $medicineObj->getAll(['is_active' => 1], '', 5000, 0);
$lowStockMedicines = $medicineObj->getLowStock();
$expiringSoon = $medicineObj->getExpiringSoon(30);

// Calculate stats
$totalItemsInStock = 0;
$outOfStockCount = 0;
$lowStockCount = count($lowStockMedicines);
$expiringSoonCount = count($expiringSoon);
$expiredCount = 0;
$inStockCount = 0;

foreach ($allMedicines as $med) {
    $qty = (int) ($med['quantity'] ?? 0);
    $totalItemsInStock += $qty;
    if ($qty === 0) {
        $outOfStockCount++;
    } elseif ($qty <= (int) ($med['min_stock_level'] ?? 10)) {
        // counted in lowStock
    } else {
        $inStockCount++;
    }

    if (!empty($med['expiry_date'])) {
        $daysLeft = calculateExpiry($med['expiry_date']);
        if ($daysLeft < 0) {
            $expiredCount++;
        }
    }
}

// Status counts for chart
$chartInStock = $inStockCount;
$chartLowStock = $lowStockCount;
$chartOutOfStock = $outOfStockCount;
$chartExpired = $expiredCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>Inventory Management - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ── Inventory Dashboard Styles ──────────────────────────── */

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .charts-row {
            display: grid;
            gap: 20px;
            margin-bottom: 24px;
        }
        .charts-row-7-5 { grid-template-columns: 7fr 5fr; }
        .charts-row-1-1 { grid-template-columns: 1fr 1fr; }

        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
        }
        .chart-card:hover {
            box-shadow: var(--shadow-md);
        }
        .chart-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
        }
        .chart-card .card-header h4 {
            font-size: 1.02rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-card .card-header h4 i {
            color: var(--primary);
            font-size: 1.15rem;
        }
        .chart-card .card-body {
            padding: 20px 22px;
        }
        .chart-container {
            position: relative;
            width: 100%;
        }
        .chart-container.chart-sm { height: 240px; }
        .chart-container.chart-md { height: 280px; }

        /* Quick Filters */
        .quick-filters {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .quick-filter-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: var(--radius-xl);
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 600;
            font-family: var(--font-sans);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .quick-filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .quick-filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(124, 179, 66, 0.3);
        }
        .quick-filter-btn .filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(0,0,0,0.08);
        }
        .quick-filter-btn.active .filter-count {
            background: rgba(255,255,255,0.25);
        }
        .quick-filter-btn.filter-danger .filter-count { background: rgba(231,76,60,0.15); color: var(--danger); }
        .quick-filter-btn.filter-warning .filter-count { background: rgba(243,156,18,0.15); color: var(--warning); }
        .quick-filter-btn.filter-out .filter-count { background: rgba(231,76,60,0.15); color: var(--danger); }

        /* Table card */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
        }
        .table-card:hover {
            box-shadow: var(--shadow-md);
        }
        .table-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .table-card .card-header h4 {
            font-size: 1.02rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-card .card-header h4 i {
            color: var(--primary);
        }
        .card-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .table-card .card-body {
            padding: 0;
        }
        .table-container {
            overflow-x: auto;
        }

        /* DataTables override */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 22px;
            font-size: 0.85rem;
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
        .dataTables_wrapper table.data-table {
            margin: 0;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            border: 1px solid var(--border-color) !important;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }

        /* Stock Level Bar */
        .stock-bar-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stock-bar-bg {
            flex: 1;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            max-width: 80px;
        }
        .stock-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        .stock-bar-fill.bar-success { background: var(--success); }
        .stock-bar-fill.bar-warning { background: var(--warning); }
        .stock-bar-fill.bar-danger  { background: var(--danger); }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .status-badge.badge-success { background: rgba(39,174,96,0.1); color: #27ae60; }
        .status-badge.badge-success::before { background: #27ae60; }
        .status-badge.badge-danger { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .status-badge.badge-danger::before { background: #e74c3c; }
        .status-badge.badge-warning { background: rgba(243,156,18,0.1); color: #e67e22; }
        .status-badge.badge-warning::before { background: #f39c12; }
        .status-badge.badge-info { background: rgba(52,152,219,0.1); color: #2980b9; }
        .status-badge.badge-info::before { background: #3498db; }
        .status-badge.badge-expired { background: rgba(142,68,173,0.1); color: #8e44ad; }
        .status-badge.badge-expired::before { background: #8e44ad; }

        /* Action buttons */
        .action-btn-group {
            display: flex;
            gap: 4px;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .action-btn.btn-view:hover { color: var(--info); border-color: var(--info); background: rgba(52,152,219,0.06); }
        .action-btn.btn-adjust:hover { color: var(--primary); border-color: var(--primary); background: var(--primary-50); }
        .action-btn.btn-expiry:hover { color: var(--warning); border-color: var(--warning); background: rgba(243,156,18,0.06); }

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

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .charts-row-7-5 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .charts-row-1-1 { grid-template-columns: 1fr; }
            .quick-filters { flex-direction: column; }
            .quick-filter-btn { justify-content: center; }
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
                <h2><i class="ri-stack-line" style="color: var(--primary); margin-right: 8px;"></i>Inventory Management</h2>
                <div class="breadcrumb">
                    <a href="/modules/dashboard/"><i class="ri-home-4-line"></i> Home</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>Inventory</span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="/modules/inventory/adjust.php" class="btn btn-primary btn-sm">
                    <i class="ri-add-line"></i> Adjust Stock
                </a>
                <button class="btn btn-outline-secondary btn-sm" onclick="exportInventoryCSV()" title="Export inventory data">
                    <i class="ri-download-line"></i> Export
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh data">
                    <i class="ri-refresh-line"></i>
                </button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 1: Top Stats Cards
             ═══════════════════════════════════════════════════════════ -->
        <div class="stats-row">
            <!-- Total Items in Stock -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-primary" style="background: linear-gradient(135deg, var(--primary-100), var(--primary-50));">
                    <i class="ri-archive-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Items in Stock</div>
                    <div class="stat-value"><?= number_format($totalItemsInStock) ?></div>
                    <div class="stat-change positive">
                        <i class="ri-stack-line"></i> Across <?= number_format(count($allMedicines)) ?> medicines
                    </div>
                </div>
            </div>

            <!-- Low Stock Items -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-warning">
                    <i class="ri-error-warning-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
                        <?= number_format($lowStockCount) ?>
                    </div>
                    <div class="stat-change <?= $lowStockCount > 0 ? 'negative' : 'positive' ?>">
                        <?php if ($lowStockCount > 0): ?>
                            <i class="ri-alarm-warning-line"></i> Needs restocking
                        <?php else: ?>
                            <i class="ri-checkbox-circle-line"></i> All stocked
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Expiring Soon -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-info">
                    <i class="ri-time-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Expiring Soon</div>
                    <div class="stat-value" style="color: <?= $expiringSoonCount > 0 ? 'var(--info)' : 'var(--success)' ?>;">
                        <?= number_format($expiringSoonCount) ?>
                    </div>
                    <div class="stat-change <?= $expiringSoonCount > 0 ? 'negative' : 'positive' ?>">
                        <?php if ($expiringSoonCount > 0): ?>
                            <i class="ri-calendar-close-line"></i> Within 30 days
                        <?php else: ?>
                            <i class="ri-shield-check-line"></i> All safe
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Out of Stock -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-danger">
                    <i class="ri-close-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value" style="color: <?= $outOfStockCount > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= number_format($outOfStockCount) ?>
                    </div>
                    <div class="stat-change <?= $outOfStockCount > 0 ? 'negative' : 'positive' ?>">
                        <?php if ($outOfStockCount > 0): ?>
                            <i class="ri-indeterminate-circle-line"></i> Urgent action needed
                        <?php else: ?>
                            <i class="ri-checkbox-circle-line"></i> All available
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 2: Stock Status Chart + Quick Info
             ═══════════════════════════════════════════════════════════ -->
        <div class="charts-row charts-row-7-5">
            <!-- Stock Status Doughnut Chart -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-pie-chart-line"></i> Stock Status Overview</h4>
                    <div style="display: flex; gap: 12px; align-items: center; font-size: 0.78rem; color: var(--text-muted);">
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#27ae60;margin-right:4px;"></span>In Stock</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f39c12;margin-right:4px;"></span>Low Stock</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#e74c3c;margin-right:4px;"></span>Out of Stock</span>
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#8e44ad;margin-right:4px;"></span>Expired</span>
                    </div>
                </div>
                <div class="card-body" style="display:flex;align-items:center;justify-content:center;">
                    <div class="chart-container chart-sm" style="max-width:320px; width:100%;">
                        <canvas id="stockStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-flashlight-line"></i> Quick Actions</h4>
                </div>
                <div class="card-body" style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="/modules/inventory/adjust.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--radius-sm);border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-secondary);font-size:0.88rem;font-weight:600;transition:var(--transition);text-decoration:none;">
                        <i class="ri-add-circle-line" style="font-size:1.3rem;color:var(--primary);"></i>
                        Adjust Stock
                    </a>
                    <a href="/modules/inventory/logs.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--radius-sm);border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-secondary);font-size:0.88rem;font-weight:600;transition:var(--transition);text-decoration:none;">
                        <i class="ri-file-list-3-line" style="font-size:1.3rem;color:var(--info);"></i>
                        Movement Logs
                    </a>
                    <a href="/modules/inventory/expiry.php" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--radius-sm);border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-secondary);font-size:0.88rem;font-weight:600;transition:var(--transition);text-decoration:none;">
                        <i class="ri-calendar-close-line" style="font-size:1.3rem;color:var(--warning);"></i>
                        Expiry Management
                    </a>
                    <a href="/modules/medicines/" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--radius-sm);border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-secondary);font-size:0.88rem;font-weight:600;transition:var(--transition);text-decoration:none;">
                        <i class="ri-medicine-bottle-line" style="font-size:1.3rem;color:#8e44ad;"></i>
                        Manage Medicines
                    </a>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 3: Quick Filters
             ═══════════════════════════════════════════════════════════ -->
        <div class="quick-filters">
            <button class="quick-filter-btn active" data-filter="all" onclick="filterInventory('all', this)">
                <i class="ri-stack-line"></i> All <span class="filter-count"><?= count($allMedicines) ?></span>
            </button>
            <button class="quick-filter-btn filter-warning" data-filter="low_stock" onclick="filterInventory('low_stock', this)">
                <i class="ri-error-warning-line"></i> Low Stock <span class="filter-count"><?= $lowStockCount ?></span>
            </button>
            <button class="quick-filter-btn filter-danger" data-filter="expiring_soon" onclick="filterInventory('expiring_soon', this)">
                <i class="ri-time-line"></i> Expiring Soon <span class="filter-count"><?= $expiringSoonCount ?></span>
            </button>
            <button class="quick-filter-btn filter-out" data-filter="out_of_stock" onclick="filterInventory('out_of_stock', this)">
                <i class="ri-close-circle-line"></i> Out of Stock <span class="filter-count"><?= $outOfStockCount ?></span>
            </button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 4: Inventory DataTable
             ═══════════════════════════════════════════════════════════ -->
        <div class="table-card">
            <div class="card-header">
                <h4><i class="ri-table-line"></i> Inventory Items</h4>
                <div class="card-header-actions">
                    <a href="/modules/inventory/adjust.php" class="btn btn-primary btn-sm">
                        <i class="ri-add-line"></i> Adjust Stock
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" onclick="exportInventoryCSV()">
                        <i class="ri-download-line"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table" id="inventoryTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Category</th>
                                <th>Batch #</th>
                                <th>Current Stock</th>
                                <th>Min Level</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allMedicines as $med): ?>
                                <?php
                                $qty = (int) ($med['quantity'] ?? 0);
                                $minLevel = (int) ($med['min_stock_level'] ?? 10);
                                $daysLeft = !empty($med['expiry_date']) ? calculateExpiry($med['expiry_date']) : 999;

                                // Determine status
                                if ($qty === 0) {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Out of Stock';
                                } elseif ($daysLeft < 0) {
                                    $statusClass = 'badge-expired';
                                    $statusText = 'Expired';
                                } elseif ($daysLeft <= 30) {
                                    $statusClass = 'badge-warning';
                                    $statusText = 'Expiring Soon';
                                } elseif ($qty <= $minLevel) {
                                    $statusClass = 'badge-warning';
                                    $statusText = 'Low Stock';
                                } else {
                                    $statusClass = 'badge-success';
                                    $statusText = 'In Stock';
                                }

                                // Stock bar
                                $stockRatio = $minLevel > 0 ? min(($qty / $minLevel) * 100, 100) : ($qty > 0 ? 100 : 0);
                                $barClass = $stockRatio <= 30 ? 'bar-danger' : ($stockRatio <= 70 ? 'bar-warning' : 'bar-success');

                                // Data attributes for filtering
                                $filterTag = 'all';
                                if ($qty === 0) $filterTag = 'out_of_stock';
                                elseif ($daysLeft < 0) $filterTag = 'out_of_stock';
                                elseif ($daysLeft <= 30) $filterTag = 'expiring_soon';
                                elseif ($qty <= $minLevel) $filterTag = 'low_stock';
                                ?>
                                <tr data-filter="<?= $filterTag ?>">
                                    <td>
                                        <div style="font-weight:600; color:var(--text-primary);"><?= sanitize($med['name'] ?? '') ?></div>
                                        <?php if (!empty($med['generic_name'])): ?>
                                            <div style="font-size:0.74rem; color:var(--text-muted);"><?= sanitize($med['generic_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:0.84rem; color:var(--text-secondary);">
                                            <?= sanitize($med['category_name'] ?? 'Uncategorized') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="background:var(--primary-50); color:var(--primary-dark); padding:2px 8px; border-radius:4px; font-size:0.8rem;">
                                            <?= sanitize($med['batch_number'] ?? 'N/A') ?>
                                        </code>
                                    </td>
                                    <td>
                                        <div class="stock-bar-wrapper">
                                            <span style="font-weight:700; min-width:32px;"><?= $qty ?></span>
                                            <div class="stock-bar-bg">
                                                <div class="stock-bar-fill <?= $barClass ?>" style="width:<?= $stockRatio ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted);"><?= $minLevel ?></td>
                                    <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                    <td style="font-size:0.82rem; color:var(--text-muted); white-space:nowrap;">
                                        <?= formatDateTime($med['updated_at'] ?? '') ?>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <a href="/modules/medicines/view.php?id=<?= $med['id'] ?>" class="action-btn btn-view" title="View details">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a href="/modules/inventory/adjust.php?medicine_id=<?= $med['id'] ?>" class="action-btn btn-adjust" title="Adjust stock">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <?php if (!empty($med['expiry_date'])): ?>
                                            <a href="/modules/inventory/expiry.php" class="action-btn btn-expiry" title="Expiry management">
                                                <i class="ri-calendar-close-line"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<!-- DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
// ─── DataTable Init ────────────────────────────────────────
const inventoryTable = $('#inventoryTable').DataTable({
    responsive: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
    order: [[3, 'asc']], // Sort by stock ascending (lowest first)
    language: {
        search: '<i class="ri-search-line"></i>',
        searchPlaceholder: 'Search inventory...',
        lengthMenu: 'Show _MENU_ items',
        info: 'Showing _START_ to _END_ of _TOTAL_ items',
        paginate: {
            previous: '<i class="ri-arrow-left-s-line"></i>',
            next: '<i class="ri-arrow-right-s-line"></i>'
        }
    },
    columnDefs: [
        { orderable: false, targets: [7] }
    ],
    drawCallback: function() {
        // Re-apply filter after draw
        const activeFilter = document.querySelector('.quick-filter-btn.active');
        if (activeFilter) {
            applyFilter(activeFilter.dataset.filter);
        }
    }
});

// ─── Quick Filter ──────────────────────────────────────────
function filterInventory(filter, btn) {
    // Update active state
    document.querySelectorAll('.quick-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilter(filter);
}

function applyFilter(filter) {
    inventoryTable.columns().search(''); // Clear existing search
    if (filter === 'all') {
        inventoryTable.search('').draw();
    } else {
        // Use custom filter on data attribute
        $.fn.dataTable.ext.search.pop();
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const row = inventoryTable.row(dataIndex).node();
            if (!row) return true;
            return filter === 'all' || row.dataset.filter === filter;
        });
        inventoryTable.draw();
        $.fn.dataTable.ext.search.pop(); // Remove after draw
    }
}

// ─── Stock Status Doughnut Chart ───────────────────────────
const stockStatusCtx = document.getElementById('stockStatusChart').getContext('2d');
new Chart(stockStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['In Stock', 'Low Stock', 'Out of Stock', 'Expired'],
        datasets: [{
            data: [<?= $chartInStock ?>, <?= $chartLowStock ?>, <?= $chartOutOfStock ?>, <?= $chartExpired ?>],
            backgroundColor: ['#27ae60', '#f39c12', '#e74c3c', '#8e44ad'],
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverBorderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#1a202c',
                titleFont: { family: 'Inter', size: 13, weight: '600' },
                bodyFont: { family: 'Inter', size: 12 },
                padding: 12,
                cornerRadius: 8,
                displayColors: true,
                boxPadding: 4,
                callbacks: {
                    label: function(ctx) {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                        return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                    }
                }
            }
        }
    },
    plugins: [{
        id: 'centerText',
        beforeDraw: function(chart) {
            const { ctx, chartArea: { width, height, top } } = chart;
            ctx.save();
            const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
            ctx.font = '700 24px Inter';
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#2d3436';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(total, width / 2 + chart.chartArea.left, top + height / 2 - 8);
            ctx.font = '500 11px Inter';
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#b2bec3';
            ctx.fillText('TOTAL ITEMS', width / 2 + chart.chartArea.left, top + height / 2 + 14);
            ctx.restore();
        }
    }]
});

// ─── Export CSV ────────────────────────────────────────────
function exportInventoryCSV() {
    const table = document.getElementById('inventoryTable');
    const rows = table.querySelectorAll('tbody tr');
    let csv = 'Medicine Name,Generic Name,Category,Batch #,Current Stock,Min Level,Status,Last Updated\n';

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 7) return;
        const name = cells[0].querySelector('div')?.textContent.trim() || cells[0].textContent.trim();
        const generic = cells[0].querySelectorAll('div')[1]?.textContent.trim() || '';
        const category = cells[1].textContent.trim();
        const batch = cells[2].textContent.trim();
        const stock = cells[3].querySelector('span')?.textContent.trim() || cells[3].textContent.trim();
        const minLevel = cells[4].textContent.trim();
        const status = cells[5].textContent.trim();
        const updated = cells[6].textContent.trim();
        csv += `"${name}","${generic}","${category}","${batch}","${stock}","${minLevel}","${status}","${updated}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `inventory_export_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);

    if (typeof showToast === 'function') {
        showToast({ type: 'success', title: 'Export Complete', message: 'Inventory data exported as CSV', icon: 'ri-download-line', bgColor: '#7CB342' });
    }
}
</script>
</body>
</html>
