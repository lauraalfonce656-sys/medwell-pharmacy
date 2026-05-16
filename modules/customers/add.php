<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Customer.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$currentPage = 'customers';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = 'Invalid request. Please try again.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: add.php');
        exit;
    }

    // Sanitize and collect input
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['city'] = trim($_POST['city'] ?? '');

    // Validation
    if (empty($formData['name'])) {
        $errors['name'] = 'Customer name is required.';
    } elseif (strlen($formData['name']) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    } elseif (strlen($formData['name']) > 100) {
        $errors['name'] = 'Name must not exceed 100 characters.';
    }

    if (!empty($formData['email'])) {
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
    }

    if (!empty($formData['phone'])) {
        if (!preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $formData['phone'])) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }
    }

    if (empty($formData['city'])) {
        $errors['city'] = 'City is required.';
    }

    // If no errors, insert
    if (empty($errors)) {
        try {
            $customerObj = new Customer();
            $result = $customerObj->create($formData);
            if ($result) {
                $_SESSION['flash_message'] = 'Customer "' . htmlspecialchars($formData['name']) . '" has been added successfully!';
                $_SESSION['flash_type'] = 'success';
                header('Location: index.php');
                exit;
            } else {
                $errors['general'] = 'Failed to add customer. Please try again.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer - MedWell Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --avocado-primary: #7CB342;
            --avocado-dark: #558B2F;
            --avocado-light: #9CCC65;
            --avocado-pale: #DCEDC8;
            --avocado-bg: #F1F8E9;
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
            position: fixed;
            left: 0; top: 0;
            z-index: 1000;
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
            padding: 0.6rem 1.5rem;
            margin: 0.1rem 0.8rem;
            border-radius: 10px;
            transition: all 0.25s ease;
        }
        .sidebar-nav .nav-item:hover { background: rgba(255,255,255,0.12); }
        .sidebar-nav .nav-item.active { background: rgba(255,255,255,0.2); }
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem; padding: 0;
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-nav .nav-item.active .nav-link { color: #fff; font-weight: 600; }
        .sidebar-nav .nav-link i { font-size: 1.15rem; width: 22px; text-align: center; }
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; }
        .top-navbar {
            background: #fff;
            padding: 0.8rem 1.5rem;
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
        /* Form Card */
        .form-card {
            background: #fff; border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(124,179,66,0.12);
            overflow: hidden;
        }
        .form-card-header {
            background: linear-gradient(135deg, var(--avocado-primary), var(--avocado-dark));
            color: #fff; padding: 1.25rem 1.5rem;
            display: flex; align-items: center; gap: 0.75rem;
        }
        .form-card-header h5 { margin: 0; font-weight: 700; }
        .form-card-body { padding: 2rem; }
        /* Premium Form Fields */
        .form-floating-custom { position: relative; margin-bottom: 1.5rem; }
        .form-floating-custom .form-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--avocado-primary); font-size: 1.1rem; z-index: 5;
        }
        .form-floating-custom .form-control,
        .form-floating-custom .form-select {
            padding-left: 44px; padding-top: 0.75rem; padding-bottom: 0.75rem;
            border: 2px solid #e8e8e8; border-radius: 10px;
            font-size: 0.9rem; transition: all 0.25s ease;
            background: #fff;
        }
        .form-floating-custom .form-control:focus,
        .form-floating-custom .form-select:focus {
            border-color: var(--avocado-primary);
            box-shadow: 0 0 0 3px rgba(124,179,66,0.15);
        }
        .form-floating-custom label {
            font-weight: 500; color: #555; margin-bottom: 0.4rem;
            font-size: 0.85rem; display: block;
        }
        .form-floating-custom .form-control.is-invalid {
            border-color: #dc3545;
        }
        .form-floating-custom .invalid-feedback {
            font-size: 0.8rem; padding-left: 4px;
        }
        textarea.form-control { resize: vertical; min-height: 100px; }
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
                        <li class="breadcrumb-item active">Add New</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small"><i class="ri-time-line"></i> <?php echo date('M d, Y'); ?></span>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header -->
            <div class="page-header">
                <h3><i class="ri-user-add-line"></i> Add New Customer</h3>
                <a href="index.php" class="btn btn-avocado-outline">
                    <i class="ri-arrow-left-line me-1"></i> Back to List
                </a>
            </div>

            <!-- General Error -->
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="ri-error-warning-line me-2"></i><?php echo $errors['general']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-card">
                        <div class="form-card-header">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px; height:44px; background:rgba(255,255,255,0.2);">
                                <i class="ri-user-add-fill fs-4"></i>
                            </div>
                            <div>
                                <h5>Customer Information</h5>
                                <small style="opacity:0.8;">Fill in the details below to register a new customer</small>
                            </div>
                        </div>
                        <div class="form-card-body">
                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Name -->
                                <div class="form-floating-custom">
                                    <label for="name"><i class="ri-user-line me-1" style="color:var(--avocado-primary)"></i> Full Name <span class="text-danger">*</span></label>
                                    <div style="position:relative;">
                                        <i class="form-icon ri-user-3-line"></i>
                                        <input type="text" name="name" id="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" placeholder="Enter customer full name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><i class="ri-close-circle-line me-1"></i><?php echo $errors['name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Email & Phone Row -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-floating-custom">
                                            <label for="email"><i class="ri-mail-line me-1" style="color:var(--avocado-primary)"></i> Email Address</label>
                                            <div style="position:relative;">
                                                <i class="form-icon ri-mail-line"></i>
                                                <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" placeholder="Enter email address" value="<?php echo htmlspecialchars($formData['email']); ?>">
                                                <?php if (isset($errors['email'])): ?>
                                                    <div class="invalid-feedback"><i class="ri-close-circle-line me-1"></i><?php echo $errors['email']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating-custom">
                                            <label for="phone"><i class="ri-phone-line me-1" style="color:var(--avocado-primary)"></i> Phone Number</label>
                                            <div style="position:relative;">
                                                <i class="form-icon ri-phone-line"></i>
                                                <input type="text" name="phone" id="phone" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" placeholder="Enter phone number" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                                                <?php if (isset($errors['phone'])): ?>
                                                    <div class="invalid-feedback"><i class="ri-close-circle-line me-1"></i><?php echo $errors['phone']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="form-floating-custom">
                                    <label for="address"><i class="ri-map-pin-line me-1" style="color:var(--avocado-primary)"></i> Address</label>
                                    <div style="position:relative;">
                                        <i class="form-icon ri-map-pin-line" style="top:1.5rem;"></i>
                                        <textarea name="address" id="address" class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" placeholder="Enter street address" rows="3"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                                        <?php if (isset($errors['address'])): ?>
                                            <div class="invalid-feedback"><i class="ri-close-circle-line me-1"></i><?php echo $errors['address']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- City -->
                                <div class="form-floating-custom">
                                    <label for="city"><i class="ri-building-line me-1" style="color:var(--avocado-primary)"></i> City <span class="text-danger">*</span></label>
                                    <div style="position:relative;">
                                        <i class="form-icon ri-building-line"></i>
                                        <input type="text" name="city" id="city" class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" placeholder="Enter city name" value="<?php echo htmlspecialchars($formData['city']); ?>" required>
                                        <?php if (isset($errors['city'])): ?>
                                            <div class="invalid-feedback"><i class="ri-close-circle-line me-1"></i><?php echo $errors['city']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                    <a href="index.php" class="btn btn-outline-secondary rounded-3">
                                        <i class="ri-close-line me-1"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-avocado px-4">
                                        <i class="ri-save-3-line me-1"></i> Save Customer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
    </script>
</body>
</html>
