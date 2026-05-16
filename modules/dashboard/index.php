<?php
/**
 * MedWell Pharmacy - Dashboard Page
 * 
 * Premium analytics dashboard with real-time stats, charts,
 * recent activity, alerts, and quick actions.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/Sale.class.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/Notification.class.php';

requireLogin();
$currentPage = 'dashboard';

// Fetch data
$medicine = new Medicine();
$sale = new Sale();
$customer = new Customer();
$supplier = new Supplier();
$notification = new Notification();

$totalMedicines = $medicine->getTotalCount();
$totalCustomers = $customer->getTotalCustomers();
$totalSuppliers = $supplier->getTotalSuppliers();
$lowStockMedicines = $medicine->getLowStock();
$expiringSoon = $medicine->getExpiringSoon(30);
$todaySales = $sale->getDailySales(date('Y-m-d'));
$monthlyRevenue = $sale->getRevenueSummary('monthly');
$recentSales = $sale->getAll([], [], 10, 0);
$topSelling = $sale->getTopSellingMedicines(5);
$unreadNotifications = $notification->getUnreadCount(getCurrentUserId());
$notifications = $notification->getByUser(getCurrentUserId(), 5);

// Check and generate alerts
$notification->checkLowStock();
$notification->checkExpiry();

// Derived values
$lowStockCount = count($lowStockMedicines);
$expiringCount = count($expiringSoon);
$todaySalesTotal = (float) ($todaySales['total_amount'] ?? 0);
$todaySalesCount = (int) ($todaySales['total_sales'] ?? 0);
$lowStockCount = count($lowStockMedicines);

// Calculate monthly revenue total
$currentMonthRevenue = 0;
if (!empty($monthlyRevenue)) {
    $currentMonth = date('Y-m');
    foreach ($monthlyRevenue as $row) {
        if (($row['period'] ?? '') === $currentMonth) {
            $currentMonthRevenue = (float) $row['revenue'];
            break;
        }
    }
    // Fallback: use most recent month
    if ($currentMonthRevenue === 0 && isset($monthlyRevenue[0])) {
        $currentMonthRevenue = (float) $monthlyRevenue[0]['revenue'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>Dashboard - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ── Dashboard-Specific Styles ───────────────────────────── */

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        /* Chart cards */
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
        .chart-container.chart-lg { height: 320px; }
        .chart-container.chart-md { height: 280px; }
        .chart-container.chart-sm { height: 240px; }

        /* Charts Row */
        .charts-row {
            display: grid;
            gap: 20px;
            margin-bottom: 24px;
        }
        .charts-row-2-1 { grid-template-columns: 2fr 1fr; }
        .charts-row-1-1 { grid-template-columns: 1fr 1fr; }
        .charts-row-7-5 { grid-template-columns: 7fr 5fr; }
        .charts-row-1-1-1 { grid-template-columns: 1fr 1fr 1fr; }

        /* Period toggle buttons */
        .period-toggle {
            display: flex;
            gap: 4px;
            background: var(--bg-body);
            border-radius: var(--radius-sm);
            padding: 3px;
        }
        .period-toggle button {
            padding: 5px 14px;
            border: none;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 600;
            font-family: var(--font-sans);
            cursor: pointer;
            transition: var(--transition);
            background: transparent;
            color: var(--text-secondary);
        }
        .period-toggle button.active,
        .period-toggle button:hover {
            background: var(--primary);
            color: #fff;
        }

        /* Top Selling List */
        .top-selling-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .top-selling-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .top-selling-rank {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .top-selling-rank.rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: #fff; }
        .top-selling-rank.rank-2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); color: #fff; }
        .top-selling-rank.rank-3 { background: linear-gradient(135deg, #CD7F32, #A0522D); color: #fff; }
        .top-selling-rank.rank-default { background: var(--primary-100); color: var(--primary-dark); }
        .top-selling-info {
            flex: 1;
            min-width: 0;
        }
        .top-selling-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .top-selling-qty {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .top-selling-bar-wrapper {
            width: 80px;
            min-width: 80px;
        }
        .top-selling-bar-bg {
            height: 6px;
            background: var(--primary-100);
            border-radius: 3px;
            overflow: hidden;
        }
        .top-selling-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            transition: width 0.6s ease;
        }

        /* Low Stock Alert List */
        .alert-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .alert-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .alert-item:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }
        .alert-item-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .alert-item-icon.icon-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }
        .alert-item-icon.icon-warning {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning);
        }
        .alert-item-icon.icon-info {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
        }
        .alert-item-info {
            flex: 1;
            min-width: 0;
        }
        .alert-item-name {
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .alert-item-detail {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .alert-item-stock {
            text-align: right;
            min-width: 60px;
        }
        .alert-item-stock .stock-value {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .alert-item-stock .stock-value.text-danger { color: var(--danger); }
        .alert-item-stock .stock-value.text-warning { color: var(--warning); }
        .alert-item-stock .stock-value.text-success { color: var(--success); }
        .alert-item-stock .stock-label {
            font-size: 0.68rem;
            color: var(--text-muted);
        }
        .alert-item-progress {
            width: 100%;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }
        .alert-item-progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.5s ease;
        }
        .alert-item-progress-fill.bg-danger { background: var(--danger); }
        .alert-item-progress-fill.bg-warning { background: var(--warning); }
        .alert-item-progress-fill.bg-success { background: var(--success); }

        /* Expiring Soon List */
        .expiry-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .expiry-badge.expired {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }
        .expiry-badge.critical {
            background: rgba(243, 156, 18, 0.1);
            color: #e67e22;
        }
        .expiry-badge.warning {
            background: rgba(243, 156, 18, 0.08);
            color: var(--warning);
        }
        .expiry-badge.safe {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        /* Quick Actions Bar */
        .quick-actions-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 20px 24px;
            margin-top: 24px;
        }
        .quick-actions-card h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quick-actions-card h4 i {
            color: var(--primary);
        }
        .quick-actions-grid {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.86rem;
            font-weight: 600;
            font-family: var(--font-sans);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .quick-action-btn i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }
        .quick-action-btn:hover {
            border-color: var(--primary);
            background: var(--primary-50);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .quick-action-btn.btn-add-medicine i { color: var(--primary); }
        .quick-action-btn.btn-new-sale i { color: var(--success); }
        .quick-action-btn.btn-add-customer i { color: var(--info); }
        .quick-action-btn.btn-view-reports i { color: var(--warning); }
        .quick-action-btn.btn-manage-inventory i { color: #8E44AD; }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            color: var(--text-muted);
            text-align: center;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        .empty-state p {
            font-size: 0.85rem;
            color: var(--text-muted);
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

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .charts-row-2-1,
            .charts-row-7-5 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .charts-row-1-1 { grid-template-columns: 1fr; }
            .quick-actions-grid { flex-direction: column; }
            .quick-action-btn { justify-content: center; }
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
                <h2>Dashboard</h2>
                <div class="breadcrumb">
                    <a href="/modules/dashboard/"><i class="ri-home-4-line"></i> Home</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>Dashboard</span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 0.82rem; color: var(--text-muted);">
                    <i class="ri-time-line"></i> <?= date('l, d M Y') ?>
                </span>
                <button class="btn btn-outline-secondary btn-sm" onclick="MedWellCharts.refreshAllCharts()" title="Refresh data">
                    <i class="ri-refresh-line"></i> Refresh
                </button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 1: Top Stats Cards
             ═══════════════════════════════════════════════════════════ -->
        <div class="stats-row">
            <!-- Total Medicines -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-primary" style="background: linear-gradient(135deg, var(--primary-100), var(--primary-50));">
                    <i class="ri-medicine-bottle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Medicines</div>
                    <div class="stat-value"><?= number_format($totalMedicines) ?></div>
                    <div class="stat-change positive">
                        <i class="ri-arrow-up-s-line"></i> Active inventory items
                    </div>
                </div>
            </div>

            <!-- Today's Sales -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-success">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-value"><?= formatCurrency($todaySalesTotal) ?></div>
                    <div class="stat-change positive">
                        <i class="ri-shopping-bag-line"></i> <?= number_format($todaySalesCount) ?> transactions
                    </div>
                </div>
            </div>

            <!-- Total Customers -->
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-info">
                    <i class="ri-user-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Customers</div>
                    <div class="stat-value"><?= number_format($totalCustomers) ?></div>
                    <div class="stat-change positive">
                        <i class="ri-group-line"></i> <?= number_format($totalSuppliers) ?> suppliers
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="stat-card card-hover-lift" style="--accent-bar: var(--danger);">
                <div class="stat-icon icon-danger">
                    <i class="ri-error-warning-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Low Stock Alert</div>
                    <div class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
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
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 2: Sales Overview + Payment Methods
             ═══════════════════════════════════════════════════════════ -->
        <div class="charts-row charts-row-2-1">
            <!-- Sales Overview (Line Chart) -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-line-chart-line"></i> Sales Overview</h4>
                    <div class="period-toggle">
                        <button class="active" onclick="MedWellCharts.updateSalesOverviewPeriod('sales-overview-chart','daily'); this.parentElement.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">Daily</button>
                        <button onclick="MedWellCharts.updateSalesOverviewPeriod('sales-overview-chart','weekly'); this.parentElement.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">Weekly</button>
                        <button onclick="MedWellCharts.updateSalesOverviewPeriod('sales-overview-chart','monthly'); this.parentElement.querySelectorAll('button').forEach(b=>b.classList.remove('active')); this.classList.add('active');">Monthly</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-lg">
                        <canvas id="sales-overview-chart" data-chart="sales-overview" data-period="daily"></canvas>
                    </div>
                </div>
            </div>

            <!-- Payment Methods (Doughnut Chart) -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-bank-card-line"></i> Payment Methods</h4>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-md">
                        <canvas id="payment-methods-chart" data-chart="payment-methods"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 3: Revenue Bar + Category Distribution Pie
             ═══════════════════════════════════════════════════════════ -->
        <div class="charts-row charts-row-1-1">
            <!-- Revenue Bar Chart -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-bar-chart-grouped-line"></i> Revenue & Expenses</h4>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-md">
                        <canvas id="revenue-chart" data-chart="revenue"></canvas>
                    </div>
                </div>
            </div>

            <!-- Category Distribution (Pie Chart) -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-pie-chart-line"></i> Category Distribution</h4>
                </div>
                <div class="card-body">
                    <div class="chart-container chart-md">
                        <canvas id="category-chart" data-chart="category-distribution"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 4: Recent Sales Table + Top Selling Medicines
             ═══════════════════════════════════════════════════════════ -->
        <div class="charts-row charts-row-7-5">
            <!-- Recent Sales -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-receipt-line"></i> Recent Sales</h4>
                    <a href="/modules/sales/" class="btn btn-outline-secondary btn-sm">
                        View All <i class="ri-arrow-right-s-line"></i>
                    </a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentSales)): ?>
                                    <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td>
                                            <a href="/modules/sales/view.php?id=<?= $sale['id'] ?>" style="font-weight: 600; color: var(--primary);">
                                                <?= sanitize($sale['invoice_number'] ?? '') ?>
                                            </a>
                                        </td>
                                        <td><?= sanitize($sale['customer_name'] ?? 'Walk-in') ?></td>
                                        <td style="font-weight: 600;"><?= formatCurrency((float) ($sale['total_amount'] ?? 0)) ?></td>
                                        <td>
                                            <span style="text-transform: capitalize; font-size: 0.82rem;">
                                                <?php
                                                $pmIcons = ['cash' => 'ri-money-dollar-circle-line', 'card' => 'ri-bank-card-line', 'mobile' => 'ri-smartphone-line'];
                                                $pm = $sale['payment_method'] ?? 'cash';
                                                ?>
                                                <i class="<?= $pmIcons[$pm] ?? 'ri-money-dollar-circle-line' ?>" style="margin-right: 4px;"></i>
                                                <?= ucfirst($pm) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $ps = $sale['payment_status'] ?? 'paid';
                                            $badgeClass = match($ps) {
                                                'paid' => 'badge-success',
                                                'partial' => 'badge-warning',
                                                'refunded' => 'badge-danger',
                                                default => 'badge-info',
                                            };
                                            ?>
                                            <span class="status-badge <?= $badgeClass ?>"><?= ucfirst($ps) ?></span>
                                        </td>
                                        <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                                            <?= formatDateTime($sale['created_at'] ?? '') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state" style="padding: 20px;">
                                                <i class="ri-receipt-line"></i>
                                                <p>No recent sales found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Selling Medicines -->
            <div class="chart-card">
                <div class="card-header">
                    <h4><i class="ri-trophy-line"></i> Top Selling</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($topSelling)): ?>
                        <?php
                        $maxQty = 0;
                        foreach ($topSelling as $item) {
                            $qty = (int) ($item['total_qty'] ?? 0);
                            if ($qty > $maxQty) $maxQty = $qty;
                        }
                        ?>
                        <div class="top-selling-list">
                            <?php foreach ($topSelling as $index => $item): ?>
                                <?php
                                $rank = $index + 1;
                                $qty = (int) ($item['total_qty'] ?? 0);
                                $revenue = (float) ($item['total_revenue'] ?? 0);
                                $barWidth = $maxQty > 0 ? round(($qty / $maxQty) * 100) : 0;
                                $rankClass = $rank <= 3 ? "rank-{$rank}" : 'rank-default';
                                ?>
                                <div class="top-selling-item">
                                    <div class="top-selling-rank <?= $rankClass ?>"><?= $rank ?></div>
                                    <div class="top-selling-info">
                                        <div class="top-selling-name"><?= sanitize($item['name'] ?? 'Unknown') ?></div>
                                        <div class="top-selling-qty"><?= number_format($qty) ?> units &middot; <?= formatCurrency($revenue) ?></div>
                                    </div>
                                    <div class="top-selling-bar-wrapper">
                                        <div class="top-selling-bar-bg">
                                            <div class="top-selling-bar-fill" style="width: <?= $barWidth ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="ri-trophy-line"></i>
                            <p>No sales data yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 5: Low Stock Alert + Expiring Soon
             ═══════════════════════════════════════════════════════════ -->
        <div class="charts-row charts-row-1-1">
            <!-- Low Stock Alert -->
            <div class="chart-card">
                <div class="card-header">
                    <h4>
                        <i class="ri-alarm-warning-line" style="color: var(--danger);"></i>
                        Low Stock Alert
                        <?php if ($lowStockCount > 0): ?>
                            <span style="background: var(--danger); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; margin-left: 4px;"><?= $lowStockCount ?></span>
                        <?php endif; ?>
                    </h4>
                    <a href="/modules/inventory/" class="btn btn-outline-secondary btn-sm">
                        Manage <i class="ri-arrow-right-s-line"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($lowStockMedicines)): ?>
                        <div class="alert-list">
                            <?php foreach (array_slice($lowStockMedicines, 0, 6) as $med): ?>
                                <?php
                                $currentQty = (int) ($med['quantity'] ?? 0);
                                $minLevel = (int) ($med['min_stock_level'] ?? 10);
                                $ratio = $minLevel > 0 ? ($currentQty / $minLevel) : 0;
                                $progressWidth = min($ratio * 100, 100);
                                $urgencyClass = $ratio <= 0.3 ? 'bg-danger' : ($ratio <= 0.7 ? 'bg-warning' : 'bg-success');
                                $textClass = $ratio <= 0.3 ? 'text-danger' : ($ratio <= 0.7 ? 'text-warning' : 'text-success');
                                $iconClass = $ratio <= 0.3 ? 'icon-danger' : ($ratio <= 0.7 ? 'icon-warning' : 'icon-info');
                                ?>
                                <div class="alert-item">
                                    <div class="alert-item-icon <?= $iconClass ?>">
                                        <i class="ri-medicine-bottle-line"></i>
                                    </div>
                                    <div class="alert-item-info">
                                        <div class="alert-item-name"><?= sanitize($med['name'] ?? 'Unknown') ?></div>
                                        <div class="alert-item-detail">Min: <?= $minLevel ?> &middot; <?= sanitize($med['category_name'] ?? 'Uncategorized') ?></div>
                                        <div class="alert-item-progress">
                                            <div class="alert-item-progress-fill <?= $urgencyClass ?>" style="width: <?= $progressWidth ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="alert-item-stock">
                                        <div class="stock-value <?= $textClass ?>"><?= $currentQty ?></div>
                                        <div class="stock-label">in stock</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="ri-checkbox-circle-line" style="color: var(--success);"></i>
                            <p>All medicines are well stocked</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Expiring Soon -->
            <div class="chart-card">
                <div class="card-header">
                    <h4>
                        <i class="ri-time-line" style="color: var(--warning);"></i>
                        Expiring Soon
                        <?php if ($expiringCount > 0): ?>
                            <span style="background: var(--warning); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 700; margin-left: 4px;"><?= $expiringCount ?></span>
                        <?php endif; ?>
                    </h4>
                    <a href="/modules/medicines/" class="btn btn-outline-secondary btn-sm">
                        View All <i class="ri-arrow-right-s-line"></i>
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($expiringSoon)): ?>
                        <div class="alert-list">
                            <?php foreach (array_slice($expiringSoon, 0, 6) as $med): ?>
                                <?php
                                $daysLeft = calculateExpiry($med['expiry_date'] ?? '');
                                $expDate = formatDate($med['expiry_date'] ?? '');
                                if ($daysLeft <= 0) {
                                    $badgeClass = 'expired';
                                    $badgeText = $daysLeft < 0 ? abs($daysLeft) . 'd overdue' : 'Today';
                                } elseif ($daysLeft <= 7) {
                                    $badgeClass = 'critical';
                                    $badgeText = $daysLeft . 'd left';
                                } elseif ($daysLeft <= 30) {
                                    $badgeClass = 'warning';
                                    $badgeText = $daysLeft . 'd left';
                                } else {
                                    $badgeClass = 'safe';
                                    $badgeText = $daysLeft . 'd left';
                                }
                                $iconClass = $daysLeft <= 7 ? 'icon-danger' : ($daysLeft <= 30 ? 'icon-warning' : 'icon-info');
                                ?>
                                <div class="alert-item">
                                    <div class="alert-item-icon <?= $iconClass ?>">
                                        <i class="ri-calendar-close-line"></i>
                                    </div>
                                    <div class="alert-item-info">
                                        <div class="alert-item-name"><?= sanitize($med['name'] ?? 'Unknown') ?></div>
                                        <div class="alert-item-detail">
                                            Expires: <?= $expDate ?> &middot; Batch: <?= sanitize($med['batch_number'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="expiry-badge <?= $badgeClass ?>">
                                            <?= $badgeText ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="ri-shield-check-line" style="color: var(--success);"></i>
                            <p>No medicines expiring soon</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             ROW 6: Quick Actions Bar
             ═══════════════════════════════════════════════════════════ -->
        <div class="quick-actions-card">
            <h4><i class="ri-flashlight-line"></i> Quick Actions</h4>
            <div class="quick-actions-grid">
                <a href="/modules/medicines/create.php" class="quick-action-btn btn-add-medicine">
                    <i class="ri-add-circle-line"></i> Add Medicine
                </a>
                <a href="/modules/pos/" class="quick-action-btn btn-new-sale">
                    <i class="ri-shopping-cart-line"></i> New Sale
                </a>
                <a href="/modules/customers/create.php" class="quick-action-btn btn-add-customer">
                    <i class="ri-user-add-line"></i> Add Customer
                </a>
                <a href="/modules/reports/" class="quick-action-btn btn-view-reports">
                    <i class="ri-bar-chart-2-line"></i> View Reports
                </a>
                <a href="/modules/inventory/" class="quick-action-btn btn-manage-inventory">
                    <i class="ri-stack-line"></i> Manage Inventory
                </a>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>
</body>
</html>
