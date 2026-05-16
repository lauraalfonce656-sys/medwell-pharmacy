<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$currentPage = 'customers';

$customerObj = new Customer();
$customerId = intval($_GET['id'] ?? 0);

if ($customerId <= 0) {
    $_SESSION['flash_message'] = 'Invalid customer ID.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$customer = $customerObj->getById($customerId);
if (!$customer) {
    $_SESSION['flash_message'] = 'Customer not found.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

// Get purchase history
$purchaseHistory = $customerObj->getPurchaseHistory($customerId);
$customerStats = $customerObj->getCustomerStats($customerId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customer - MedWell Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --avocado-primary: #7CB342;
            --avocado-dark: #558B2F;
            --avocado-light: #9CCC65;
            --avocado-pale: #DCEDC8;
            --avocado-bg: #F1F8E9;
            --avocado-accent: #8BC34A;
            --sidebar-width: 260px;
        }
        body {
            background: var(--avocado-bg);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #33691E 0%, var(--avocado-dark) 40%, var(--avocado-primary) 100%);
            min-height: 100vh;
            position: fixed; left: 0; top: 0; z-index: 1000;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            text-align: center;
        }
        .sidebar-brand h4 { color: #fff; font-weight: 700; margin: 0; }
        .sidebar-brand small { color: rgba(255,255,255,0.7); font-size: 0.75rem; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav .nav-item {
            padding: 0.6rem 1.5rem; margin: 0.1rem 0.8rem;
            border-radius: 10px; transition: all 0.25s ease;
        }
        .sidebar-nav .nav-item:hover { background: rgba(255,255,255,0.12); }
        .sidebar-nav .nav-item.active { background: rgba(255,255,255,0.2); }
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.85); font-size: 0.9rem; padding: 0;
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-nav .nav-item.active .nav-link { color: #fff; font-weight: 600; }
        .sidebar-nav .nav-link i { font-size: 1.15rem; width: 22px; text-align: center; }
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; }
        .top-navbar {
            background: #fff; padding: 0.8rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        }
        .top-navbar .breadcrumb { margin: 0; font-size: 0.85rem; }
        .top-navbar .breadcrumb-item a { color: var(--avocado-dark); text-decoration: none; }
        .top-navbar .breadcrumb-item.active { color: var(--avocado-primary); }
        .page-content { padding: 1.5rem; }
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
        }
        .page-header h3 {
            font-weight: 700; color: #2E3B2F; margin: 0;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .page-header h3 i { color: var(--avocado-primary); }
        .btn-avocado {
            background: var(--avocado-primary); color: #fff; border: none;
            border-radius: 8px; padding: 0.55rem 1.3rem; font-weight: 600;
            font-size: 0.9rem; transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(124,179,66,0.3);
        }
        .btn-avocado:hover {
            background: var(--avocado-dark); color: #fff;
            transform: translateY(-1px); box-shadow: 0 4px 14px rgba(124,179,66,0.4);
        }
        .btn-avocado-outline {
            background: transparent; color: var(--avocado-primary);
            border: 2px solid var(--avocado-primary); border-radius: 8px;
            padding: 0.45rem 1.2rem; font-weight: 600; font-size: 0.85rem;
            transition: all 0.25s ease;
        }
        .btn-avocado-outline:hover { background: var(--avocado-primary); color: #fff; }

        /* Profile Card */
        .profile-card {
            background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12);
            overflow: hidden;
        }
        .profile-card-banner {
            background: linear-gradient(135deg, var(--avocado-primary), #33691E);
            height: 100px;
            position: relative;
        }
        .profile-card-body {
            padding: 0 1.5rem 1.5rem;
            position: relative;
        }
        .profile-avatar {
            width: 90px; height: 90px; border-radius: 50%;
            background: #fff; border: 4px solid #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 700; color: var(--avocado-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: -45px;
            position: relative;
            z-index: 2;
        }
        .profile-name {
            font-size: 1.4rem; font-weight: 700; color: #2E3B2F;
            margin-top: 0.75rem; margin-bottom: 0.25rem;
        }
        .profile-id {
            font-size: 0.8rem; color: #999; font-weight: 500;
        }
        .profile-detail {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 0; color: #555; font-size: 0.88rem;
        }
        .profile-detail i {
            color: var(--avocado-primary); width: 20px; text-align: center;
        }
        .loyalty-display {
            background: linear-gradient(135deg, var(--avocado-pale), #C5E1A5);
            border-radius: 12px; padding: 1rem 1.25rem;
            margin-top: 1rem; text-align: center;
            border: 1px solid rgba(124,179,66,0.2);
        }
        .loyalty-display .points-value {
            font-size: 2rem; font-weight: 800; color: var(--avocado-dark);
            line-height: 1.2;
        }
        .loyalty-display .points-label {
            font-size: 0.78rem; color: var(--avocado-dark);
            text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;
        }
        .loyalty-display i { font-size: 1.3rem; color: #F9A825; }

        /* Stats Cards */
        .stat-card {
            background: #fff; border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12);
            transition: all 0.3s ease;
            position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 4px; height: 100%; background: var(--avocado-primary);
            border-radius: 4px 0 0 4px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .stat-card .stat-value {
            font-size: 1.4rem; font-weight: 800; color: #2E3B2F; line-height: 1.2;
        }
        .stat-card .stat-label {
            font-size: 0.75rem; color: #777; font-weight: 500;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* Status Badges */
        .badge-active {
            background: var(--avocado-pale); color: var(--avocado-dark);
            font-weight: 600; padding: 0.35rem 0.8rem; border-radius: 20px; font-size: 0.78rem;
        }
        .badge-inactive {
            background: #FFEBEE; color: #C62828;
            font-weight: 600; padding: 0.35rem 0.8rem; border-radius: 20px; font-size: 0.78rem;
        }

        /* Table Card */
        .table-card {
            background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12); overflow: hidden;
        }
        .table-card .card-header {
            background: linear-gradient(135deg, var(--avocado-primary), var(--avocado-dark));
            color: #fff; padding: 1rem 1.5rem; border: none;
            display: flex; align-items: center; justify-content: space-between;
        }
        .table-card .card-header h5 {
            margin: 0; font-weight: 700;
            display: flex; align-items: center; gap: 0.5rem;
        }
        table.dataTable thead th {
            background: var(--avocado-pale) !important; color: #33691E !important;
            font-weight: 700; font-size: 0.82rem;
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid var(--avocado-light) !important;
        }
        table.dataTable tbody td {
            vertical-align: middle; font-size: 0.88rem; color: #444;
        }
        table.dataTable tbody tr:hover {
            background-color: var(--avocado-bg) !important;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex; gap: 0.5rem; flex-wrap: wrap;
        }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="ri-capsule-fill"></i> MedWell</h4>
            <small>Pharmacy Management System</small>
        </div>
        <ul class="sidebar-nav nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/dashboard/"><i class="ri-dashboard-3-line"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/medicines/"><i class="ri-medicine-bottle-line"></i> Medicines</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/sales/"><i class="ri-shopping-cart-2-line"></i> Sales</a>
            </li>
            <li class="nav-item active">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/customers/"><i class="ri-group-line"></i> Customers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/suppliers/"><i class="ri-truck-line"></i> Suppliers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/reports/"><i class="ri-bar-chart-grouped-line"></i> Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/settings/"><i class="ri-settings-4-line"></i> Settings</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div>
                <button class="btn btn-sm btn-link d-lg-none me-2" id="sidebarToggle"><i class="ri-menu-line fs-5"></i></button>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>"><i class="ri-home-4-line"></i> Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Customers</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($customer['name']); ?></li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small"><i class="ri-time-line"></i> <?php echo date('M d, Y'); ?></span>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                    <i class="ri-<?php echo ($_SESSION['flash_type'] ?? 'success') === 'success' ? 'check-double-line' : 'error-warning-line'; ?> me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <h3><i class="ri-user-heart-line"></i> Customer Profile</h3>
                <div class="quick-actions">
                    <a href="edit.php?id=<?php echo $customer['id']; ?>" class="btn btn-avocado">
                        <i class="ri-edit-line me-1"></i> Edit Customer
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/sales/add.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-avocado-outline">
                        <i class="ri-shopping-cart-add-line me-1"></i> New Sale
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary rounded-3">
                        <i class="ri-arrow-left-line me-1"></i> Back
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column: Profile Card -->
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-card-banner"></div>
                        <div class="profile-card-body text-center">
                            <div class="profile-avatar mx-auto">
                                <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                            <div class="profile-id">Customer #<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></div>

                            <!-- Status -->
                            <div class="mt-2">
                                <?php if (($customer['is_active'] ?? 1) == 1): ?>
                                    <span class="badge-active"><i class="ri-checkbox-blank-circle-fill me-1" style="font-size:0.45rem;"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive"><i class="ri-checkbox-blank-circle-fill me-1" style="font-size:0.45rem;"></i>Inactive</span>
                                <?php endif; ?>
                            </div>

                            <hr style="border-color: #eee;">

                            <!-- Contact Details -->
                            <div class="text-start">
                                <div class="profile-detail">
                                    <i class="ri-mail-line"></i>
                                    <span><?php echo htmlspecialchars($customer['email'] ?? 'No email provided'); ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="ri-phone-line"></i>
                                    <span><?php echo htmlspecialchars($customer['phone'] ?? 'No phone provided'); ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="ri-map-pin-2-line"></i>
                                    <span><?php echo htmlspecialchars($customer['address'] ?? 'No address provided'); ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="ri-building-line"></i>
                                    <span><?php echo htmlspecialchars($customer['city'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="profile-detail">
                                    <i class="ri-calendar-line"></i>
                                    <span>Joined <?php echo date('M d, Y', strtotime($customer['created_at'] ?? 'now')); ?></span>
                                </div>
                            </div>

                            <!-- Loyalty Points -->
                            <div class="loyalty-display">
                                <i class="ri-star-fill"></i>
                                <div class="points-value"><?php echo number_format($customer['loyalty_points'] ?? 0); ?></div>
                                <div class="points-label">Loyalty Points</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Stats & Purchase History -->
                <div class="col-lg-8">
                    <!-- Stats Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon" style="background: var(--avocado-pale); color: var(--avocado-dark);">
                                        <i class="ri-shopping-bag-line"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value">$<?php echo number_format($customerStats['total_purchases'] ?? 0, 2); ?></div>
                                        <div class="stat-label">Total Purchases</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon" style="background: #E3F2FD; color: #1565C0;">
                                        <i class="ri-bar-chart-horizontal-line"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value">$<?php echo number_format($customerStats['avg_purchase'] ?? 0, 2); ?></div>
                                        <div class="stat-label">Avg Purchase Value</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon" style="background: #FFF8E1; color: #F57F17;">
                                        <i class="ri-calendar-check-line"></i>
                                    </div>
                                    <div>
                                        <div class="stat-value" style="font-size: 1.1rem;"><?php echo $customerStats['last_visit'] ? date('M d, Y', strtotime($customerStats['last_visit'])) : 'N/A'; ?></div>
                                        <div class="stat-label">Last Visit Date</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase History Table -->
                    <div class="table-card">
                        <div class="card-header">
                            <h5><i class="ri-history-line"></i> Purchase History</h5>
                            <span class="badge bg-light text-dark"><?php echo count($purchaseHistory); ?> transactions</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="purchaseHistoryTable" class="table table-hover mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice #</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Payment Method</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($purchaseHistory)): ?>
                                            <?php foreach ($purchaseHistory as $purchase): ?>
                                                <tr>
                                                    <td>
                                                        <span class="fw-medium"><?php echo date('M d, Y', strtotime($purchase['sale_date'] ?? $purchase['created_at'] ?? 'now')); ?></span>
                                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($purchase['sale_date'] ?? $purchase['created_at'] ?? 'now')); ?></small>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo BASE_URL; ?>/modules/sales/view.php?id=<?php echo $purchase['id']; ?>" class="fw-semibold" style="color: var(--avocado-dark); text-decoration: none;">
                                                            INV-<?php echo str_pad($purchase['id'], 5, '0', STR_PAD_LEFT); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge rounded-pill" style="background: var(--avocado-pale); color: var(--avocado-dark);">
                                                            <?php echo intval($purchase['item_count'] ?? 1); ?> item(s)
                                                        </span>
                                                    </td>
                                                    <td class="fw-bold" style="color: var(--avocado-dark);">
                                                        $<?php echo number_format($purchase['total_amount'] ?? 0, 2); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $paymentMethod = $purchase['payment_method'] ?? 'cash';
                                                        $paymentIcons = [
                                                            'cash' => 'ri-money-dollar-circle-line',
                                                            'card' => 'ri-credit-card-line',
                                                            'insurance' => 'ri-shield-check-line',
                                                            'mobile' => 'ri-smartphone-line'
                                                        ];
                                                        $paymentColors = [
                                                            'cash' => '#2E7D32',
                                                            'card' => '#1565C0',
                                                            'insurance' => '#6A1B9A',
                                                            'mobile' => '#E65100'
                                                        ];
                                                        $icon = $paymentIcons[$paymentMethod] ?? 'ri-bank-line';
                                                        $color = $paymentColors[$paymentMethod] ?? '#555';
                                                        ?>
                                                        <span style="color: <?php echo $color; ?>;">
                                                            <i class="<?php echo $icon; ?> me-1"></i><?php echo ucfirst($paymentMethod); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <div style="color: var(--avocado-primary);">
                                                        <i class="ri-shopping-bag-line" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                                    </div>
                                                    <p class="text-muted mt-2 mb-0">No purchase history yet</p>
                                                    <a href="<?php echo BASE_URL; ?>/modules/sales/add.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-avocado btn-sm mt-2">
                                                        <i class="ri-shopping-cart-add-line me-1"></i> Create First Sale
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#purchaseHistoryTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    search: '<i class="ri-search-line"></i>',
                    searchPlaceholder: 'Search purchases...',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ transactions',
                    paginate: {
                        previous: '<i class="ri-arrow-left-s-line"></i>',
                        next: '<i class="ri-arrow-right-s-line"></i>'
                    }
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
            });

            document.getElementById('sidebarToggle')?.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('show');
            });
        });
    </script>
</body>
</html>
