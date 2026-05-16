<?php
/**
 * MedWell Pharmacy - Sales History Page
 * 
 * DataTable of all sales with filters, receipt view,
 * and refund action (admin only).
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Sale.class.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'sales';

$currentUser = getCurrentUser();
$isAdmin     = ($currentUser['role'] ?? '') === 'admin';

$sale     = new Sale();
$customer = new Customer();
$settings = getSettings();
$taxRate  = defined('TAX_RATE') ? TAX_RATE : 18;

// Filters
$startDate      = $_GET['start_date'] ?? date('Y-m-01');
$endDate        = $_GET['end_date'] ?? date('Y-m-d');
$paymentMethod  = $_GET['payment_method'] ?? '';
$paymentStatus  = $_GET['payment_status'] ?? '';

$filters = [];
if (!empty($paymentMethod)) $filters['payment_method'] = $paymentMethod;
if (!empty($paymentStatus)) $filters['payment_status'] = $paymentStatus;

$dateRange = [];
if (!empty($startDate)) $dateRange['start'] = $startDate;
if (!empty($endDate))   $dateRange['end'] = $endDate;

$sales = $sale->getAll($filters, $dateRange, 500, 0);

// Stats
$totalSales   = count($sales);
$totalRevenue  = array_sum(array_map(fn($s) => (float) ($s['total_amount'] ?? 0), $sales));
$totalTax      = array_sum(array_map(fn($s) => (float) ($s['tax_amount'] ?? 0), $sales));
$totalDiscount = array_sum(array_map(fn($s) => (float) ($s['discount'] ?? 0), $sales));

$pharmacyName = $settings['pharmacy_name'] ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>Sales History - <?= sanitize($pharmacyName) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">

    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        /* ─── Page-Specific Styles ──────────────────────────────── */

        .sales-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .sales-page-header .header-left h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .sales-page-header .header-left .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .sales-page-header .header-left .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
        }
        .sales-page-header .header-left .breadcrumb a:hover {
            color: var(--primary);
        }
        .sales-page-header .header-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Summary Stats Row */
        .sales-stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .sales-stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .sales-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 0 4px 4px 0;
        }
        .sales-stat-card:nth-child(1)::before { background: linear-gradient(180deg, var(--primary), var(--primary-dark)); }
        .sales-stat-card:nth-child(2)::before { background: linear-gradient(180deg, #27ae60, #219a52); }
        .sales-stat-card:nth-child(3)::before { background: linear-gradient(180deg, #3498db, #2980b9); }
        .sales-stat-card:nth-child(4)::before { background: linear-gradient(180deg, #e67e22, #d35400); }

        .sales-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .sales-stat-card .stat-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .sales-stat-card:nth-child(1) .stat-icon { background: var(--primary-100); color: var(--primary-dark); }
        .sales-stat-card:nth-child(2) .stat-icon { background: rgba(39,174,96,0.1); color: #27ae60; }
        .sales-stat-card:nth-child(3) .stat-icon { background: rgba(52,152,219,0.1); color: #3498db; }
        .sales-stat-card:nth-child(4) .stat-icon { background: rgba(230,126,34,0.1); color: #e67e22; }

        .sales-stat-card .stat-content {
            flex: 1;
        }
        .sales-stat-card .stat-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }
        .sales-stat-card .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        /* Filters Card */
        .filters-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }
        .filters-card .filters-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .filters-card .filters-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .filters-card .filters-header h4 i {
            color: var(--primary);
        }
        .filters-card .filters-toggle {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--primary);
            cursor: pointer;
            background: none;
            border: none;
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .filters-card .filters-toggle:hover {
            text-decoration: underline;
        }
        .filters-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .filter-group input,
        .filter-group select {
            padding: 9px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            font-weight: 500;
            outline: none;
            transition: var(--transition);
            font-family: var(--font-sans);
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.12);
        }
        .filter-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23636e72' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px 14px;
            padding-right: 36px;
            cursor: pointer;
        }
        .filter-btn {
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            border: none;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 12px rgba(124, 179, 66, 0.3);
        }
        .filter-btn-primary:hover {
            box-shadow: 0 6px 18px rgba(124, 179, 66, 0.4);
            transform: translateY(-1px);
        }
        .filter-btn-outline {
            background: transparent;
            border: 1.5px solid var(--border-color);
            color: var(--text-secondary);
        }
        .filter-btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Data Table Card */
        .data-table-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .data-table-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table-card .card-header h4 {
            font-size: 1.02rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .data-table-card .card-header h4 i {
            color: var(--primary);
            font-size: 1.15rem;
        }
        .data-table-card .card-body {
            padding: 0;
        }

        /* DataTables override */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.88rem;
        }
        .data-table thead th {
            background: var(--primary-50);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .data-table tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        .data-table tbody tr {
            transition: var(--transition);
        }
        .data-table tbody tr:hover {
            background: var(--primary-50);
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Invoice link */
        .invoice-link {
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.86rem;
        }
        .invoice-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Payment method badge */
        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .payment-badge.cash {
            background: rgba(39, 174, 96, 0.08);
            color: #27ae60;
        }
        .payment-badge.card {
            background: rgba(52, 152, 219, 0.08);
            color: #2980b9;
        }
        .payment-badge.mobile {
            background: rgba(155, 89, 182, 0.08);
            color: #8e44ad;
        }

        /* Action buttons */
        .action-btns {
            display: flex;
            gap: 6px;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        .action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .action-btn.btn-view:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .action-btn.btn-refund:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(231, 76, 60, 0.08);
        }

        /* Status badge */
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
        .status-badge.badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }
        .status-badge.badge-success::before {
            background: #27ae60;
        }
        .status-badge.badge-danger {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        .status-badge.badge-danger::before {
            background: #e74c3c;
        }
        .status-badge.badge-warning {
            background: rgba(243, 156, 18, 0.1);
            color: #e67e22;
        }
        .status-badge.badge-warning::before {
            background: #f39c12;
        }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            color: var(--text-muted);
            text-align: center;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.3;
        }
        .empty-state h4 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        .empty-state p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Refund Confirmation Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            padding: 20px;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-dialog {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 440px;
            transform: scale(0.9) translateY(20px);
            transition: var(--transition);
            overflow: hidden;
        }
        .modal-overlay.active .modal-dialog {
            transform: scale(1) translateY(0);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-header h3 i {
            color: var(--danger);
        }
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        .modal-close:hover {
            background: var(--primary-50);
            color: var(--danger);
        }
        .modal-body {
            padding: 24px;
        }
        .modal-body p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .modal-body .warning-box {
            background: rgba(231, 76, 60, 0.06);
            border: 1px solid rgba(231, 76, 60, 0.2);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            font-size: 0.84rem;
            color: var(--danger);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .modal-body .warning-box i {
            font-size: 1.1rem;
            margin-top: 1px;
        }
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
        }
        .modal-btn {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: var(--font-sans);
            border: none;
        }
        .modal-btn-cancel {
            background: var(--bg-body);
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        .modal-btn-cancel:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .modal-btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        .modal-btn-danger:hover {
            box-shadow: 0 6px 18px rgba(231, 76, 60, 0.45);
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .sales-stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sales-stats-row { grid-template-columns: 1fr; }
            .filters-row { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-group input,
            .filter-group select { width: 100%; }
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
        <div class="sales-page-header">
            <div class="header-left">
                <h2>Sales History</h2>
                <div class="breadcrumb">
                    <a href="/modules/dashboard/"><i class="ri-home-4-line"></i> Home</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>POS</span>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>Sales</span>
                </div>
            </div>
            <div class="header-right">
                <a href="/modules/pos/" class="btn btn-primary" style="padding:10px 20px; border-radius:var(--radius-sm); border:none; background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff; font-size:0.88rem; font-weight:600; cursor:pointer; transition:var(--transition); font-family:var(--font-sans); text-decoration:none; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 15px rgba(124,179,66,0.3);">
                    <i class="ri-add-line"></i> New Sale
                </a>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             Stats Row
             ═══════════════════════════════════════════════════════════ -->
        <div class="sales-stats-row">
            <!-- Total Sales -->
            <div class="sales-stat-card">
                <div class="stat-icon">
                    <i class="ri-receipt-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value"><?= number_format($totalSales) ?></div>
                </div>
            </div>
            <!-- Total Revenue -->
            <div class="sales-stat-card">
                <div class="stat-icon">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value"><?= formatCurrency($totalRevenue) ?></div>
                </div>
            </div>
            <!-- Total Tax -->
            <div class="sales-stat-card">
                <div class="stat-icon">
                    <i class="ri-government-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Tax Collected</div>
                    <div class="stat-value"><?= formatCurrency($totalTax) ?></div>
                </div>
            </div>
            <!-- Total Discount -->
            <div class="sales-stat-card">
                <div class="stat-icon">
                    <i class="ri-discount-percent-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Discount</div>
                    <div class="stat-value"><?= formatCurrency($totalDiscount) ?></div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             Filters
             ═══════════════════════════════════════════════════════════ -->
        <div class="filters-card">
            <div class="filters-header">
                <h4><i class="ri-filter-3-line"></i> Filters</h4>
                <a href="/modules/pos/sales.php" class="filters-toggle">
                    <i class="ri-refresh-line"></i> Reset
                </a>
            </div>
            <form method="GET" action="/modules/pos/sales.php">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= sanitize($startDate) ?>">
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= sanitize($endDate) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="">All Methods</option>
                            <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Card</option>
                            <option value="mobile" <?= $paymentMethod === 'mobile' ? 'selected' : '' ?>>Mobile Money</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="payment_status">
                            <option value="">All Statuses</option>
                            <option value="paid" <?= $paymentStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="partial" <?= $paymentStatus === 'partial' ? 'selected' : '' ?>>Partial</option>
                            <option value="refunded" <?= $paymentStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="filter-group" style="flex-direction:row; align-items:flex-end; gap:8px;">
                        <button type="submit" class="filter-btn filter-btn-primary">
                            <i class="ri-search-line"></i> Apply
                        </button>
                        <a href="/modules/pos/sales.php" class="filter-btn filter-btn-outline" style="text-decoration:none;">
                            <i class="ri-close-line"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             Sales DataTable
             ═══════════════════════════════════════════════════════════ -->
        <div class="data-table-card">
            <div class="card-header">
                <h4><i class="ri-table-line"></i> All Sales</h4>
                <div style="display:flex; gap:8px; align-items:center;">
                    <span style="font-size:0.82rem; color:var(--text-muted);">
                        <?= number_format($totalSales) ?> records
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div style="overflow-x:auto;">
                    <table class="data-table" id="salesTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sales)): ?>
                                <?php foreach ($sales as $s): ?>
                                    <?php
                                    $invoiceNumber = $s['invoice_number'] ?? '';
                                    $customerName  = $s['customer_name'] ?? 'Walk-in';
                                    $totalAmount   = (float) ($s['total_amount'] ?? 0);
                                    $pm            = $s['payment_method'] ?? 'cash';
                                    $ps            = $s['payment_status'] ?? 'paid';
                                    $createdAt     = $s['created_at'] ?? '';
                                    $saleId        = (int) ($s['id'] ?? 0);

                                    // Count items (from DB join if available, otherwise show dash)
                                    $itemCount     = $s['item_count'] ?? '-';

                                    $pmBadge = match($pm) {
                                        'cash' => 'cash',
                                        'card' => 'card',
                                        'mobile' => 'mobile',
                                        default => 'cash'
                                    };
                                    $pmIcon = match($pm) {
                                        'cash' => 'ri-money-dollar-circle-line',
                                        'card' => 'ri-bank-card-line',
                                        'mobile' => 'ri-smartphone-line',
                                        default => 'ri-money-dollar-circle-line'
                                    };

                                    $psBadge = match($ps) {
                                        'paid' => 'badge-success',
                                        'partial' => 'badge-warning',
                                        'refunded' => 'badge-danger',
                                        default => 'badge-success'
                                    };
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="/modules/pos/receipt.php?id=<?= $saleId ?>" class="invoice-link">
                                                <?= sanitize($invoiceNumber) ?>
                                            </a>
                                        </td>
                                        <td><?= sanitize($customerName) ?></td>
                                        <td style="text-align:center;"><?= $itemCount ?></td>
                                        <td style="font-weight:700;"><?= formatCurrency($totalAmount) ?></td>
                                        <td>
                                            <span class="payment-badge <?= $pmBadge ?>">
                                                <i class="<?= $pmIcon ?>"></i>
                                                <?= ucfirst($pm) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $psBadge ?>">
                                                <?= ucfirst($ps) ?>
                                            </span>
                                        </td>
                                        <td style="font-size:0.82rem; color:var(--text-muted); white-space:nowrap;">
                                            <?= formatDateTime($createdAt) ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="/modules/pos/receipt.php?id=<?= $saleId ?>" class="action-btn btn-view" title="View Receipt">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a href="/modules/pos/receipt.php?id=<?= $saleId ?>&autoprint=1" class="action-btn" title="Print Receipt" target="_blank">
                                                    <i class="ri-printer-line"></i>
                                                </a>
                                                <?php if ($isAdmin && $ps !== 'refunded'): ?>
                                                <button class="action-btn btn-refund" title="Refund Sale" onclick="openRefundModal(<?= $saleId ?>, '<?= sanitize($invoiceNumber) ?>')">
                                                    <i class="ri-refund-line"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="ri-receipt-line"></i>
                                            <h4>No sales found</h4>
                                            <p>No sales match your current filter criteria.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<!-- ═══════════════════════════════════════════════════════════
     REFUND CONFIRMATION MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="refundModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><i class="ri-error-warning-line"></i> Confirm Refund</h3>
            <button class="modal-close" onclick="closeRefundModal()"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to refund sale <strong id="refundInvoiceDisplay">#INV-0001</strong>?</p>
            <div class="warning-box">
                <i class="ri-alert-line"></i>
                <div>
                    <strong>This action cannot be undone.</strong> The sale will be marked as refunded and stock will be restored for all items in this sale.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn-cancel" onclick="closeRefundModal()">Cancel</button>
            <button class="modal-btn modal-btn-danger" id="confirmRefundBtn" onclick="processRefund()">
                <i class="ri-refund-line"></i> Confirm Refund
            </button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/js/app.js"></script>

<script>
    // ─── DataTables Initialization ─────────────────────────────────
    $(document).ready(function() {
        if ($.fn.DataTable) {
            $('#salesTable').DataTable({
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[6, 'desc']], // Sort by date descending
                language: {
                    search: '',
                    searchPlaceholder: 'Search sales...',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ sales',
                    paginate: {
                        first: '<i class="ri-skip-back-mini-line"></i>',
                        last: '<i class="ri-skip-forward-mini-line"></i>',
                        next: '<i class="ri-arrow-right-s-line"></i>',
                        previous: '<i class="ri-arrow-left-s-line"></i>'
                    }
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                columnDefs: [
                    { orderable: false, targets: [7] } // Disable sort on actions column
                ]
            });
        }
    });

    // ─── Refund Modal ──────────────────────────────────────────────
    let refundSaleId = null;

    function openRefundModal(saleId, invoiceNumber) {
        refundSaleId = saleId;
        document.getElementById('refundInvoiceDisplay').textContent = '#' + invoiceNumber;
        document.getElementById('refundModal').classList.add('active');
    }

    function closeRefundModal() {
        document.getElementById('refundModal').classList.remove('active');
        refundSaleId = null;
    }

    function processRefund() {
        if (!refundSaleId) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('/api/sales/refund.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                _token: csrfToken,
                sale_id: refundSaleId
            })
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                closeRefundModal();
                // Show success toast
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Refund Processed', message: 'Sale has been refunded successfully.' });
                } else {
                    alert('Sale refunded successfully!');
                }
                // Reload page
                setTimeout(() => window.location.reload(), 1500);
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'error', title: 'Refund Failed', message: result.message || 'Failed to process refund.' });
                } else {
                    alert('Refund failed: ' + (result.message || 'Unknown error'));
                }
            }
        })
        .catch(err => {
            console.error('Refund error:', err);
            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'error', title: 'Error', message: 'Network error. Please try again.' });
            } else {
                alert('Network error. Please try again.');
            }
        });
    }

    // Close modal on overlay click
    document.getElementById('refundModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeRefundModal();
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRefundModal();
    });
</script>

</body>
</html>
