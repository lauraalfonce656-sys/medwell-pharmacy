<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$currentPage = 'customers';
$customerObj = new Customer();
$search = $_GET['search'] ?? '';
$customers = $customerObj->getAll($search, 100, 0);
$totalCustomers = $customerObj->getTotalCustomers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - MedWell Pharmacy</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Remix Icons -->
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
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #33691E 0%, var(--avocado-dark) 40%, var(--avocado-primary) 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }
        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            text-align: center;
        }
        .sidebar-brand h4 {
            color: #fff;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.7);
            font-size: 0.75rem;
        }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav .nav-item {
            padding: 0.6rem 1.5rem;
            margin: 0.1rem 0.8rem;
            border-radius: 10px;
            transition: all 0.25s ease;
        }
        .sidebar-nav .nav-item:hover {
            background: rgba(255,255,255,0.12);
        }
        .sidebar-nav .nav-item.active {
            background: rgba(255,255,255,0.2);
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-nav .nav-item.active .nav-link {
            color: #fff;
            font-weight: 600;
        }
        .sidebar-nav .nav-link i { font-size: 1.15rem; width: 22px; text-align: center; }
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 0;
            min-height: 100vh;
        }
        /* Top Navbar */
        .top-navbar {
            background: #fff;
            padding: 0.8rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 6px rgba(0,0,0,0.04);
        }
        .top-navbar .breadcrumb {
            margin: 0;
            font-size: 0.85rem;
        }
        .top-navbar .breadcrumb-item a {
            color: var(--avocado-dark);
            text-decoration: none;
        }
        .top-navbar .breadcrumb-item.active { color: var(--avocado-primary); }
        /* Page Content */
        .page-content { padding: 1.5rem; }
        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h3 {
            font-weight: 700;
            color: #2E3B2F;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .page-header h3 i { color: var(--avocado-primary); }
        .btn-avocado {
            background: var(--avocado-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.55rem 1.3rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(124,179,66,0.3);
        }
        .btn-avocado:hover {
            background: var(--avocado-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(124,179,66,0.4);
        }
        .btn-avocado-outline {
            background: transparent;
            color: var(--avocado-primary);
            border: 2px solid var(--avocado-primary);
            border-radius: 8px;
            padding: 0.45rem 1.2rem;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.25s ease;
        }
        .btn-avocado-outline:hover {
            background: var(--avocado-primary);
            color: #fff;
        }
        /* Stats Cards */
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--avocado-primary);
            border-radius: 4px 0 0 4px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #2E3B2F;
            line-height: 1.2;
        }
        .stat-card .stat-label {
            font-size: 0.78rem;
            color: #777;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Search Bar */
        .search-bar-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12);
            margin-bottom: 1.5rem;
        }
        .search-input-group {
            position: relative;
        }
        .search-input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--avocado-primary);
            font-size: 1.1rem;
        }
        .search-input-group input {
            padding-left: 42px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: border-color 0.25s ease;
        }
        .search-input-group input:focus {
            border-color: var(--avocado-primary);
            box-shadow: 0 0 0 3px rgba(124,179,66,0.15);
        }
        /* Data Table Card */
        .table-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12);
            overflow: hidden;
        }
        .table-card .card-header {
            background: linear-gradient(135deg, var(--avocado-primary), var(--avocado-dark));
            color: #fff;
            padding: 1rem 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-card .card-header h5 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        /* DataTable Overrides */
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--avocado-primary);
            box-shadow: 0 0 0 3px rgba(124,179,66,0.15);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            padding: 0.3rem 0.6rem;
        }
        table.dataTable thead th {
            background: var(--avocado-pale) !important;
            color: #33691E !important;
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--avocado-light) !important;
        }
        table.dataTable tbody td {
            vertical-align: middle;
            font-size: 0.88rem;
            color: #444;
        }
        table.dataTable tbody tr:hover {
            background-color: var(--avocado-bg) !important;
        }
        /* Status Badges */
        .badge-active {
            background: var(--avocado-pale);
            color: var(--avocado-dark);
            font-weight: 600;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        .badge-inactive {
            background: #FFEBEE;
            color: #C62828;
            font-weight: 600;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }
        .action-btn-view {
            background: #E3F2FD;
            color: #1565C0;
        }
        .action-btn-view:hover { background: #1565C0; color: #fff; }
        .action-btn-edit {
            background: #FFF8E1;
            color: #F57F17;
        }
        .action-btn-edit:hover { background: #F57F17; color: #fff; }
        .action-btn-delete {
            background: #FFEBEE;
            color: #C62828;
        }
        .action-btn-delete:hover { background: #C62828; color: #fff; }
        /* Loyalty Points Badge */
        .loyalty-badge {
            background: linear-gradient(135deg, var(--avocado-pale), #C5E1A5);
            color: var(--avocado-dark);
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.78rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .loyalty-badge i { font-size: 0.7rem; }
        /* Responsive */
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
                <button class="btn btn-sm btn-link d-lg-none me-2" id="sidebarToggle">
                    <i class="ri-menu-line fs-5"></i>
                </button>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>"><i class="ri-home-4-line"></i> Home</a></li>
                        <li class="breadcrumb-item active">Customers</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small"><i class="ri-time-line"></i> <?php echo date('M d, Y'); ?></span>
                <div class="dropdown">
                    <a href="#" class="text-decoration-none d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <div class="bg-success bg-opacity-25 rounded-circle p-2"><i class="ri-user-3-line text-success"></i></div>
                        <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/settings/"><i class="ri-settings-4-line me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="ri-logout-box-r-line me-2"></i>Logout</a></li>
                    </ul>
                </div>
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
                <h3><i class="ri-group-line"></i> Customers</h3>
                <a href="add.php" class="btn btn-avocado">
                    <i class="ri-user-add-line me-1"></i> Add Customer
                </a>
            </div>

            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background: var(--avocado-pale); color: var(--avocado-dark);">
                                <i class="ri-group-line"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?php echo number_format($totalCustomers['total'] ?? 0); ?></div>
                                <div class="stat-label">Total Customers</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background: #E8F5E9; color: #2E7D32;">
                                <i class="ri-user-follow-line"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?php echo number_format($totalCustomers['active'] ?? 0); ?></div>
                                <div class="stat-label">Active Customers</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background: #FFF8E1; color: #F57F17;">
                                <i class="ri-star-line"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?php echo number_format($totalCustomers['loyalty_points'] ?? 0); ?></div>
                                <div class="stat-label">Total Loyalty Points</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background: #E3F2FD; color: #1565C0;">
                                <i class="ri-money-dollar-circle-line"></i>
                            </div>
                            <div>
                                <div class="stat-value">$<?php echo number_format($totalCustomers['total_purchases'] ?? 0, 2); ?></div>
                                <div class="stat-label">Total Purchases Value</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-bar-card">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-lg-5 col-md-6">
                        <div class="search-input-group">
                            <i class="ri-search-line"></i>
                            <input type="text" name="search" class="form-control" placeholder="Search customers by name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <select name="status_filter" class="form-select border-2" style="border-color: #e8e8e8;">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-2">
                        <button type="submit" class="btn btn-avocado w-100">
                            <i class="ri-filter-3-line me-1"></i> Filter
                        </button>
                    </div>
                    <div class="col-lg-2 text-end">
                        <a href="index.php" class="btn btn-avocado-outline">
                            <i class="ri-refresh-line me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Customers DataTable -->
            <div class="table-card">
                <div class="card-header">
                    <h5><i class="ri-table-line"></i> Customer Directory</h5>
                    <span class="badge bg-light text-dark"><?php echo count($customers); ?> records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="customersTable" class="table table-hover mb-0" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                    <th>Loyalty Points</th>
                                    <th>Total Purchases</th>
                                    <th>Status</th>
                                    <th style="width:140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($customers)): ?>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td><span class="fw-semibold text-muted">#<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:36px; height:36px; background: var(--avocado-pale); color: var(--avocado-dark); font-weight:700; font-size:0.8rem;">
                                                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><i class="ri-mail-line me-1" style="color: var(--avocado-primary);"></i><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="text-muted"><i class="ri-phone-line me-1" style="color: var(--avocado-primary);"></i><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span><i class="ri-map-pin-2-line me-1" style="color: var(--avocado-primary);"></i><?php echo htmlspecialchars($customer['city'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="loyalty-badge"><i class="ri-star-fill"></i> <?php echo number_format($customer['loyalty_points'] ?? 0); ?></span>
                                            </td>
                                            <td class="fw-semibold">$<?php echo number_format($customer['total_purchases'] ?? 0, 2); ?></td>
                                            <td>
                                                <?php if (($customer['is_active'] ?? 1) == 1): ?>
                                                    <span class="badge-active"><i class="ri-checkbox-blank-circle-fill me-1" style="font-size:0.45rem;"></i>Active</span>
                                                <?php else: ?>
                                                    <span class="badge-inactive"><i class="ri-checkbox-blank-circle-fill me-1" style="font-size:0.45rem;"></i>Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="view.php?id=<?php echo $customer['id']; ?>" class="action-btn action-btn-view" title="View"><i class="ri-eye-line"></i></a>
                                                    <a href="edit.php?id=<?php echo $customer['id']; ?>" class="action-btn action-btn-edit" title="Edit"><i class="ri-pencil-line"></i></a>
                                                    <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this customer?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                        <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                        <button type="submit" class="action-btn action-btn-delete" title="Delete"><i class="ri-delete-bin-6-line"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <div style="color: var(--avocado-primary);">
                                                <i class="ri-group-line" style="font-size: 3rem; opacity: 0.3;"></i>
                                            </div>
                                            <p class="text-muted mt-2 mb-0">No customers found</p>
                                            <a href="add.php" class="btn btn-avocado btn-sm mt-2"><i class="ri-user-add-line me-1"></i> Add First Customer</a>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#customersTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                order: [[0, 'desc']],
                language: {
                    search: '<i class="ri-search-line"></i>',
                    searchPlaceholder: 'Search...',
                    lengthMenu: 'Show _MENU_ entries',
                    info: 'Showing _START_ to _END_ of _TOTAL_ customers',
                    paginate: {
                        previous: '<i class="ri-arrow-left-s-line"></i>',
                        next: '<i class="ri-arrow-right-s-line"></i>'
                    }
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
            });

            // Sidebar toggle for mobile
            $('#sidebarToggle').on('click', function() {
                $('#sidebar').toggleClass('show');
            });
        });
    </script>
</body>
</html>
