<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Report.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$currentPage = 'reports';
$report = new Report();

// Quick stats
$todayRevenue = $report->getTodayRevenue();
$monthRevenue = $report->getMonthRevenue();
$profitMargin = $report->getTotalProfitMargin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - MedWell Pharmacy</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --mw-green: #7CB342;
            --mw-green-dark: #689F38;
            --mw-green-light: #9CCC65;
            --mw-green-bg: #f1f8e9;
            --mw-green-50: #fafdf6;
        }
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #558B2F 0%, #33691E 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-brand .brand-icon {
            width: 42px; height: 42px;
            background: var(--mw-green-light);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #33691E;
        }
        .sidebar-brand h5 {
            color: #fff; margin: 0; font-weight: 700; font-size: 1.15rem;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.65); font-size: 0.75rem;
        }
        .sidebar-nav { padding: 16px 12px; }
        .sidebar-nav .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 16px; border-radius: 10px;
            color: rgba(255,255,255,0.8); text-decoration: none;
            font-size: 0.9rem; margin-bottom: 4px; transition: all 0.2s;
        }
        .sidebar-nav .nav-item:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .sidebar-nav .nav-item.active {
            background: rgba(255,255,255,0.2); color: #fff; font-weight: 600;
        }
        .sidebar-nav .nav-item i { font-size: 1.2rem; }
        /* Main */
        .main-content { margin-left: 260px; }
        .top-bar {
            background: #fff; padding: 16px 30px;
            border-bottom: 1px solid #e8ecf1;
            display: flex; justify-content: space-between; align-items: center;
        }
        .top-bar h4 { margin: 0; font-weight: 700; color: #2d3748; }
        .content-area { padding: 30px; }

        /* Quick Stats */
        .stat-card {
            background: #fff; border-radius: 16px; padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e8ecf1;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .stat-card h3 { font-weight: 800; margin: 0; font-size: 1.6rem; color: #2d3748; }
        .stat-card p { margin: 0; color: #718096; font-size: 0.85rem; }

        /* Report Cards */
        .report-card {
            background: #fff; border-radius: 16px; padding: 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e8ecf1;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .report-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 4px; border-radius: 16px 16px 0 0;
        }
        .report-card:hover { transform: translateY(-6px); box-shadow: 0 12px 28px rgba(0,0,0,0.12); }
        .report-card .icon-box {
            width: 64px; height: 64px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; margin-bottom: 20px;
        }
        .report-card h5 { font-weight: 700; color: #2d3748; margin-bottom: 8px; }
        .report-card p { color: #718096; font-size: 0.88rem; margin-bottom: 20px; min-height: 40px; }
        .report-card .btn-view {
            background: var(--mw-green); color: #fff; border: none;
            border-radius: 10px; padding: 10px 20px; font-weight: 600;
            font-size: 0.88rem; transition: all 0.2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .report-card .btn-view:hover { background: var(--mw-green-dark); color: #fff; }

        /* Color Variants */
        .report-card.card-green::before { background: var(--mw-green); }
        .report-card.card-green .icon-box { background: var(--mw-green-bg); color: var(--mw-green); }
        .report-card.card-blue::before { background: #2196F3; }
        .report-card.card-blue .icon-box { background: #E3F2FD; color: #2196F3; }
        .report-card.card-orange::before { background: #FF9800; }
        .report-card.card-orange .icon-box { background: #FFF3E0; color: #FF9800; }
        .report-card.card-red::before { background: #F44336; }
        .report-card.card-red .icon-box { background: #FFEBEE; color: #F44336; }
        .report-card.card-purple::before { background: #9C27B0; }
        .report-card.card-purple .icon-box { background: #F3E5F5; color: #9C27B0; }
        .report-card.card-teal::before { background: #009688; }
        .report-card.card-teal .icon-box { background: #E0F2F1; color: #009688; }

        .section-title {
            font-size: 1.1rem; font-weight: 700; color: #2d3748;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: var(--mw-green); }

        @media (max-width: 991px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
        @media print {
            .sidebar, .top-bar { display: none !important; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="ri-capsule-fill"></i></div>
            <div>
                <h5>MedWell</h5>
                <small>Pharmacy System</small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="../../index.php" class="nav-item"><i class="ri-dashboard-3-line"></i> Dashboard</a>
            <a href="../medicines/index.php" class="nav-item"><i class="ri-capsule-line"></i> Medicines</a>
            <a href="../sales/index.php" class="nav-item"><i class="ri-shopping-cart-line"></i> Sales</a>
            <a href="../purchases/index.php" class="nav-item"><i class="ri-truck-line"></i> Purchases</a>
            <a href="../customers/index.php" class="nav-item"><i class="ri-user-line"></i> Customers</a>
            <a href="../suppliers/index.php" class="nav-item"><i class="ri-store-2-line"></i> Suppliers</a>
            <a href="index.php" class="nav-item active"><i class="ri-bar-chart-box-line"></i> Reports</a>
            <a href="../settings/index.php" class="nav-item"><i class="ri-settings-3-line"></i> Settings</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h4><i class="ri-bar-chart-box-line" style="color:var(--mw-green)"></i> Reports & Analytics</h4>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted"><i class="ri-time-line"></i> <?php echo date('M d, Y'); ?></span>
            </div>
        </div>

        <div class="content-area">
            <!-- Quick Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:var(--mw-green-bg);color:var(--mw-green);">
                                <i class="ri-money-dollar-circle-line"></i>
                            </div>
                            <div>
                                <p>Today's Revenue</p>
                                <h3>$<?php echo number_format($todayRevenue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#E3F2FD;color:#2196F3;">
                                <i class="ri-calendar-line"></i>
                            </div>
                            <div>
                                <p>This Month Revenue</p>
                                <h3>$<?php echo number_format($monthRevenue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#FFF3E0;color:#FF9800;">
                                <i class="ri-percent-line"></i>
                            </div>
                            <div>
                                <p>Total Profit Margin</p>
                                <h3><?php echo number_format($profitMargin, 1); ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Cards -->
            <div class="section-title"><i class="ri-folder-5-line"></i> Available Reports</div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="report-card card-green">
                        <div class="icon-box"><i class="ri-line-chart-line"></i></div>
                        <h5>Sales Report</h5>
                        <p>Analyze sales trends, revenue patterns, and transaction data over any time period.</p>
                        <a href="sales.php" class="btn-view"><i class="ri-arrow-right-line"></i> View Report</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="report-card card-blue">
                        <div class="icon-box"><i class="ri-money-dollar-circle-line"></i></div>
                        <h5>Profit Report</h5>
                        <p>Track revenue versus costs, profit margins, and financial performance metrics.</p>
                        <a href="profit.php" class="btn-view"><i class="ri-arrow-right-line"></i> View Report</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="report-card card-orange">
                        <div class="icon-box"><i class="ri-archive-line"></i></div>
                        <h5>Inventory Report</h5>
                        <p>Monitor stock levels, inventory valuation, and identify low-stock items.</p>
                        <a href="inventory.php" class="btn-view"><i class="ri-arrow-right-line"></i> View Report</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="report-card card-red">
                        <div class="icon-box"><i class="ri-error-warning-line"></i></div>
                        <h5>Expiry Report</h5>
                        <p>Identify expired and soon-to-expire medicines to prevent losses and ensure safety.</p>
                        <a href="expiry.php" class="btn-view"><i class="ri-arrow-right-line"></i> View Report</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="report-card card-purple">
                        <div class="icon-box"><i class="ri-user-line"></i></div>
                        <h5>Customer Report</h5>
                        <p>Analyze customer purchasing behavior, top buyers, and loyalty metrics.</p>
                        <a href="customers.php" class="btn-view"><i class="ri-arrow-right-line"></i> View Report</a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="report-card card-teal">
                        <div class="icon-box"><i class="ri-truck-line"></i></div>
                        <h5>Supplier Report</h5>
                        <p>Review supplier performance, stock distribution, and procurement data.</p>
                        <a href="suppliers.php" class="btn-view"><i class="ri-arrow-right-line"></i> View Report</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
