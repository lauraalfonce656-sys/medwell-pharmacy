<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/User.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole('admin');
$currentPage = 'settings';
$user = new User();
$allUsers = $user->getAll();
$settings = getSettings();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ─── POST Processing ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid CSRF token. Please try again.'];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = $_POST['form_action'] ?? '';

    switch ($action) {

        // ── Save Pharmacy Information ────────────────────────────────────────────
        case 'save_pharmacy':
            $pharmacyData = [
                'pharmacy_name'   => trim($_POST['pharmacy_name'] ?? ''),
                'pharmacy_address'=> trim($_POST['pharmacy_address'] ?? ''),
                'pharmacy_phone'  => trim($_POST['pharmacy_phone'] ?? ''),
                'pharmacy_email'  => trim($_POST['pharmacy_email'] ?? ''),
                'pharmacy_tin'    => trim($_POST['pharmacy_tin'] ?? ''),
                'operating_hours' => trim($_POST['operating_hours'] ?? ''),
            ];

            // Logo upload
            if (isset($_FILES['pharmacy_logo']) && $_FILES['pharmacy_logo']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                $fileTmp  = $_FILES['pharmacy_logo']['tmp_name'];
                $fileSize = $_FILES['pharmacy_logo']['size'];
                $fileType = mime_content_type($fileTmp);
                $fileExt  = strtolower(pathinfo($_FILES['pharmacy_logo']['name'], PATHINFO_EXTENSION));

                if (!in_array($fileType, $allowedTypes)) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Invalid file type. Only PNG, JPG, GIF, and WebP are allowed.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                if ($fileSize > $maxSize) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'File too large. Maximum size is 2MB.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }

                $uploadDir = __DIR__ . '/../../uploads/logos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = 'pharmacy_logo_' . time() . '.' . $fileExt;
                $destPath = $uploadDir . $fileName;

                if (move_uploaded_file($fileTmp, $destPath)) {
                    $pharmacyData['pharmacy_logo'] = $fileName;
                } else {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Failed to upload logo.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
            }

            try {
                $pdo = getDB();
                foreach ($pharmacyData as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2");
                    $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
                }
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Pharmacy information updated successfully.'];
            } catch (Exception $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error saving pharmacy information: ' . $e->getMessage()];
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        // ── Save System Settings ────────────────────────────────────────────────
        case 'save_system':
            $systemData = [
                'currency'           => trim($_POST['currency'] ?? 'TZS'),
                'tax_rate'           => floatval($_POST['tax_rate'] ?? 0),
                'low_stock_threshold'=> intval($_POST['low_stock_threshold'] ?? 10),
                'expiry_warning_days'=> intval($_POST['expiry_warning_days'] ?? 30),
                'date_format'        => trim($_POST['date_format'] ?? 'Y-m-d'),
                'invoice_prefix'     => trim($_POST['invoice_prefix'] ?? 'INV'),
                'receipt_footer'     => trim($_POST['receipt_footer'] ?? ''),
                'dark_mode_default'  => isset($_POST['dark_mode_default']) ? '1' : '0',
            ];
            try {
                $pdo = getDB();
                foreach ($systemData as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2");
                    $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
                }
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'System settings updated successfully.'];
            } catch (Exception $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error saving system settings: ' . $e->getMessage()];
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        // ── Add / Edit User ─────────────────────────────────────────────────────
        case 'save_user':
            $userId   = intval($_POST['user_id'] ?? 0);
            $userData = [
                'username'  => trim($_POST['username'] ?? ''),
                'email'     => trim($_POST['email'] ?? ''),
                'full_name' => trim($_POST['full_name'] ?? ''),
                'phone'     => trim($_POST['phone'] ?? ''),
                'role'      => trim($_POST['role'] ?? 'pharmacist'),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            $password = $_POST['password'] ?? '';

            try {
                $pdo = getDB();

                // Check duplicate username / email (exclude current user on edit)
                $dupStmt = $pdo->prepare("SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id");
                $dupStmt->execute([':u' => $userData['username'], ':e' => $userData['email'], ':id' => $userId]);
                if ($dupStmt->fetch()) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Username or email already exists.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }

                if ($userId > 0) {
                    // Update
                    $sql = "UPDATE users SET username=:username, email=:email, full_name=:full_name, phone=:phone, role=:role, is_active=:is_active";
                    $params = $userData;
                    $params['id'] = $userId;
                    if (!empty($password)) {
                        $sql .= ", password=:password";
                        $params['password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id=:id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User updated successfully.'];
                } else {
                    // Insert
                    if (empty($password)) {
                        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Password is required for new users.'];
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, phone, password, role, is_active) VALUES (:username, :email, :full_name, :phone, :password, :role, :is_active)");
                    $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->execute($userData);
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User created successfully.'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error saving user: ' . $e->getMessage()];
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        // ── Delete User ─────────────────────────────────────────────────────────
        case 'delete_user':
            $deleteId = intval($_POST['delete_user_id'] ?? 0);
            if ($deleteId === intval($_SESSION['user_id'])) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'You cannot delete your own account.'];
            } elseif ($deleteId > 0) {
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                    $stmt->execute([':id' => $deleteId]);
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User deleted successfully.'];
                } catch (Exception $e) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error deleting user: ' . $e->getMessage()];
                }
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        // ── Toggle User Active ──────────────────────────────────────────────────
        case 'toggle_user':
            $toggleId = intval($_POST['toggle_user_id'] ?? 0);
            if ($toggleId === intval($_SESSION['user_id'])) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'You cannot deactivate your own account.'];
            } elseif ($toggleId > 0) {
                try {
                    $pdo = getDB();
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :id");
                    $stmt->execute([':id' => $toggleId]);
                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User status toggled successfully.'];
                } catch (Exception $e) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error toggling user status: ' . $e->getMessage()];
                }
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        // ── Update Password ─────────────────────────────────────────────────────
        case 'update_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword     = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($currentPassword, $row['password'])) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Current password is incorrect.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                if (strlen($newPassword) < 8) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'New password must be at least 8 characters.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                if ($newPassword !== $confirmPassword) {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'New passwords do not match.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }

                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                $upd->execute([':p' => $hash, ':id' => $_SESSION['user_id']]);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Password updated successfully.'];
            } catch (Exception $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Error updating password: ' . $e->getMessage()];
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    }
}

// Refresh settings after possible POST update
$settings = getSettings();
$allUsers = $user->getAll();

// Helper to get setting value
function s($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — MedWell Pharmacy</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Remix Icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --avocado:       #7CB342;
            --avocado-dark:  #558B2F;
            --avocado-light: #9CCC65;
            --avocado-50:    #f1f8e9;
            --avocado-100:   #dcedc8;
            --avocado-200:   #c5e1a5;
            --avocado-600:   #689F38;
            --avocado-700:   #558B2F;
            --avocado-800:   #33691E;
            --sidebar-width: 260px;
        }

        * { font-family: 'Inter', sans-serif; }

        body {
            background: #f4f6f9;
            min-height: 100vh;
        }

        /* ─── Sidebar ────────────────────────────────────────── */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--avocado-800) 0%, var(--avocado-dark) 100%);
            color: #fff;
            z-index: 1040;
            transition: transform .3s ease;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,.12);
            display: flex; align-items: center; gap: .75rem;
        }
        .sidebar-brand i { font-size: 1.75rem; color: var(--avocado-light); }
        .sidebar-brand span { font-size: 1.15rem; font-weight: 700; letter-spacing: .3px; }
        .sidebar-nav { padding: .75rem 0; }
        .sidebar-nav .nav-item {
            padding: .6rem 1.5rem;
            display: flex; align-items: center; gap: .75rem;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            font-size: .9rem;
            font-weight: 500;
            transition: all .2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav .nav-item:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar-nav .nav-item.active {
            color: #fff;
            background: rgba(255,255,255,.12);
            border-left-color: var(--avocado-light);
        }
        .sidebar-nav .nav-item i { font-size: 1.1rem; width: 22px; text-align: center; }

        /* ─── Main Content ───────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 0;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            background: #fff;
            padding: .85rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 1020;
        }
        .top-bar h5 { margin: 0; font-weight: 700; color: #1f2937; }
        .top-bar .badge-admin {
            background: var(--avocado-50);
            color: var(--avocado-700);
            font-weight: 600;
            font-size: .75rem;
            padding: .35rem .75rem;
            border-radius: 50rem;
        }

        /* ─── Settings Tabs (Pills) ──────────────────────────── */
        .nav-pills-settings {
            background: #fff;
            border-radius: .75rem;
            padding: .5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            flex-wrap: wrap;
            gap: .25rem;
        }
        .nav-pills-settings .nav-link {
            color: #6b7280;
            font-weight: 600;
            font-size: .875rem;
            padding: .6rem 1.15rem;
            border-radius: .5rem;
            transition: all .25s ease;
            display: flex; align-items: center; gap: .5rem;
            border: none;
        }
        .nav-pills-settings .nav-link i { font-size: 1rem; }
        .nav-pills-settings .nav-link:hover {
            color: var(--avocado-dark);
            background: var(--avocado-50);
        }
        .nav-pills-settings .nav-link.active {
            background: var(--avocado) !important;
            color: #fff !important;
            box-shadow: 0 2px 8px rgba(124,179,66,.35);
        }

        /* ─── Cards ──────────────────────────────────────────── */
        .settings-card {
            background: #fff;
            border: none;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .settings-card .card-header {
            background: var(--avocado-50);
            border-bottom: 2px solid var(--avocado-200);
            padding: 1rem 1.25rem;
            border-radius: .75rem .75rem 0 0 !important;
        }
        .settings-card .card-header h6 {
            margin: 0;
            font-weight: 700;
            color: var(--avocado-800);
            display: flex; align-items: center; gap: .5rem;
        }

        /* ─── Form Controls ──────────────────────────────────── */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--avocado-light);
            box-shadow: 0 0 0 .2rem rgba(124,179,66,.2);
        }
        .form-label {
            font-weight: 600;
            font-size: .82rem;
            color: #374151;
            margin-bottom: .35rem;
        }

        /* ─── Buttons ────────────────────────────────────────── */
        .btn-avocado {
            background: var(--avocado);
            color: #fff;
            border: none;
            font-weight: 600;
            padding: .55rem 1.5rem;
            border-radius: .5rem;
            transition: all .2s;
        }
        .btn-avocado:hover {
            background: var(--avocado-dark);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(124,179,66,.35);
        }
        .btn-avocado-outline {
            background: transparent;
            color: var(--avocado);
            border: 1.5px solid var(--avocado);
            font-weight: 600;
            padding: .5rem 1.35rem;
            border-radius: .5rem;
            transition: all .2s;
        }
        .btn-avocado-outline:hover {
            background: var(--avocado-50);
            color: var(--avocado-dark);
            border-color: var(--avocado-dark);
        }

        /* ─── Logo Preview ───────────────────────────────────── */
        .logo-preview {
            width: 120px; height: 120px;
            border: 2px dashed var(--avocado-200);
            border-radius: .75rem;
            display: flex; align-items: center; justify-content: center;
            background: var(--avocado-50);
            overflow: hidden;
            transition: border-color .2s;
        }
        .logo-preview:hover { border-color: var(--avocado); }
        .logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .logo-preview .placeholder-icon { font-size: 2.5rem; color: var(--avocado-200); }

        /* ─── Toggle Switch ──────────────────────────────────── */
        .form-check-input:checked {
            background-color: var(--avocado);
            border-color: var(--avocado);
        }

        /* ─── Badges ─────────────────────────────────────────── */
        .badge-role-admin   { background: #EF4444; color: #fff; }
        .badge-role-pharmacist { background: var(--avocado); color: #fff; }
        .badge-role-cashier { background: #F59E0B; color: #fff; }
        .badge-status-active   { background: #D1FAE5; color: #065F46; }
        .badge-status-inactive { background: #FEE2E2; color: #991B1B; }

        /* ─── Password Strength ──────────────────────────────── */
        .strength-bar { height: 6px; border-radius: 3px; transition: all .3s; }
        .strength-weak   { width: 33%; background: #EF4444; }
        .strength-medium { width: 66%; background: #F59E0B; }
        .strength-strong { width: 100%; background: var(--avocado); }

        /* ─── DataTables Override ────────────────────────────── */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db;
            border-radius: .375rem;
            padding: .3rem .75rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--avocado-light);
            box-shadow: 0 0 0 .15rem rgba(124,179,66,.2);
            outline: none;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d5db;
            border-radius: .375rem;
            padding: .25rem .5rem;
        }
        table.dataTable tbody tr:hover { background: var(--avocado-50) !important; }

        /* ─── Modal ──────────────────────────────────────────── */
        .modal-header { background: var(--avocado-50); border-bottom: 2px solid var(--avocado-200); }
        .modal-header .modal-title { color: var(--avocado-800); font-weight: 700; }

        /* ─── Flash Message ──────────────────────────────────── */
        .alert-flash {
            border-radius: .6rem;
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }
        .alert-flash.alert-success { background: #D1FAE5; color: #065F46; }
        .alert-flash.alert-danger  { background: #FEE2E2; color: #991B1B; }

        /* ─── Section Title ──────────────────────────────────── */
        .section-title {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--avocado-600);
            margin-bottom: 1rem;
            padding-bottom: .5rem;
            border-bottom: 1px solid var(--avocado-100);
        }

        /* ─── Responsive ─────────────────────────────────────── */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }

        /* ─── Action Buttons ─────────────────────────────────── */
        .action-btn {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: .375rem;
            transition: all .2s;
            font-size: .85rem;
            border: none;
        }
        .action-btn-edit    { background: #DBEAFE; color: #1D4ED8; }
        .action-btn-edit:hover    { background: #1D4ED8; color: #fff; }
        .action-btn-toggle  { background: #D1FAE5; color: #065F46; }
        .action-btn-toggle:hover  { background: #065F46; color: #fff; }
        .action-btn-delete  { background: #FEE2E2; color: #991B1B; }
        .action-btn-delete:hover  { background: #991B1B; color: #fff; }
    </style>
</head>
<body>

<!-- ╔══════════════════════════════════════════════════════════════════════╗
     ║  SIDEBAR                                                            ║
     ╚══════════════════════════════════════════════════════════════════════╝ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="ri-capsule-fill"></i>
        <span>MedWell Pharmacy</span>
    </div>
    <nav class="sidebar-nav">
        <a href="../../dashboard" class="nav-item"><i class="ri-dashboard-3-line"></i> Dashboard</a>
        <a href="../medicines" class="nav-item"><i class="ri-medicine-bottle-line"></i> Medicines</a>
        <a href="../sales" class="nav-item"><i class="ri-shopping-cart-2-line"></i> Sales</a>
        <a href="../purchases" class="nav-item"><i class="ri-truck-line"></i> Purchases</a>
        <a href="../inventory" class="nav-item"><i class="ri-archive-2-line"></i> Inventory</a>
        <a href="../reports" class="nav-item"><i class="ri-bar-chart-grouped-line"></i> Reports</a>
        <a href="../customers" class="nav-item"><i class="ri-group-line"></i> Customers</a>
        <a href="../suppliers" class="nav-item"><i class="ri-store-3-line"></i> Suppliers</a>
        <a href="index.php" class="nav-item active"><i class="ri-settings-4-line"></i> Settings</a>
        <a href="../../logout" class="nav-item"><i class="ri-logout-box-r-line"></i> Logout</a>
    </nav>
</aside>

<!-- ╔══════════════════════════════════════════════════════════════════════╗
     ║  MAIN CONTENT                                                      ║
     ╚══════════════════════════════════════════════════════════════════════╝ -->
<div class="main-content">

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-light d-lg-none" id="sidebarToggle">
                <i class="ri-menu-line"></i>
            </button>
            <h5><i class="ri-settings-4-fill" style="color:var(--avocado)"></i> Settings</h5>
            <span class="badge-admin"><i class="ri-shield-keyhole-line"></i> Admin</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted" style="font-size:.85rem">
                <i class="ri-time-line"></i> <?= date('M d, Y h:i A') ?>
            </span>
        </div>
    </div>

    <!-- Content Area -->
    <div class="p-3 p-lg-4">

        <!-- Flash Message -->
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-flash alert-<?= htmlspecialchars($_SESSION['flash_message']['type']) ?> alert-dismissible fade show" role="alert">
            <i class="ri-<?= $_SESSION['flash_message']['type'] === 'success' ? 'checkbox-circle-fill' : 'error-warning-fill' ?>"></i>
            <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message']); endif; ?>

        <!-- Tab Navigation (Pills) -->
        <ul class="nav nav-pills-settings mb-4" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pharmacy-tab" data-bs-toggle="pill" data-bs-target="#pharmacyPane" type="button" role="tab" aria-controls="pharmacyPane" aria-selected="true">
                    <i class="ri-hospital-fill"></i> Pharmacy Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="pill" data-bs-target="#systemPane" type="button" role="tab" aria-controls="systemPane" aria-selected="false">
                    <i class="ri-tools-fill"></i> System Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="users-tab" data-bs-toggle="pill" data-bs-target="#usersPane" type="button" role="tab" aria-controls="usersPane" aria-selected="false">
                    <i class="ri-group-fill"></i> User Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="password-tab" data-bs-toggle="pill" data-bs-target="#passwordPane" type="button" role="tab" aria-controls="passwordPane" aria-selected="false">
                    <i class="ri-lock-password-fill"></i> Password Update
                </button>
            </li>
        </ul>

        <!-- Tab Panes -->
        <div class="tab-content" id="settingsTabContent">

            <!-- ════════════════════════════════════════════════════════════
                 TAB 1 : PHARMACY INFORMATION
                 ════════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade show active" id="pharmacyPane" role="tabpanel" aria-labelledby="pharmacy-tab">
                <form method="POST" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="form_action" value="save_pharmacy">

                    <div class="row g-4">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <div class="settings-card">
                                <div class="card-header">
                                    <h6><i class="ri-building-2-line"></i> Pharmacy Details</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Pharmacy Name <span class="text-danger">*</span></label>
                                            <input type="text" name="pharmacy_name" class="form-control" value="<?= htmlspecialchars(s('pharmacy_name', 'MedWell Pharmacy')) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">TIN / Tax Number</label>
                                            <input type="text" name="pharmacy_tin" class="form-control" value="<?= htmlspecialchars(s('pharmacy_tin')) ?>" placeholder="e.g. 123-456-789">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Address <span class="text-danger">*</span></label>
                                            <textarea name="pharmacy_address" class="form-control" rows="2" required><?= htmlspecialchars(s('pharmacy_address')) ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ri-phone-line"></i></span>
                                                <input type="tel" name="pharmacy_phone" class="form-control" value="<?= htmlspecialchars(s('pharmacy_phone')) ?>" placeholder="+255 700 000 000" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ri-mail-line"></i></span>
                                                <input type="email" name="pharmacy_email" class="form-control" value="<?= htmlspecialchars(s('pharmacy_email')) ?>" placeholder="info@medwell.co.tz" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Operating Hours</label>
                                            <input type="text" name="operating_hours" class="form-control" value="<?= htmlspecialchars(s('operating_hours', 'Mon-Fri: 8:00 AM - 8:00 PM | Sat: 9:00 AM - 5:00 PM | Sun: Closed')) ?>" placeholder="e.g. Mon-Fri: 8AM - 8PM">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column — Logo -->
                        <div class="col-lg-4">
                            <div class="settings-card">
                                <div class="card-header">
                                    <h6><i class="ri-image-line"></i> Pharmacy Logo</h6>
                                </div>
                                <div class="card-body p-4 text-center">
                                    <div class="logo-preview mx-auto mb-3" id="logoPreview">
                                        <?php $logoFile = s('pharmacy_logo'); ?>
                                        <?php if ($logoFile && file_exists(__DIR__ . '/../../uploads/logos/' . $logoFile)): ?>
                                            <img src="<?= '../../uploads/logos/' . htmlspecialchars($logoFile) ?>?t=<?= time() ?>" alt="Logo" id="logoImg">
                                        <?php else: ?>
                                            <div class="placeholder-icon" id="logoPlaceholder">
                                                <i class="ri-image-add-line"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <label for="logoInput" class="btn-avocado-outline btn-sm cursor-pointer" style="cursor:pointer">
                                        <i class="ri-upload-2-line"></i> Choose Logo
                                    </label>
                                    <input type="file" name="pharmacy_logo" id="logoInput" class="d-none" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp">
                                    <div class="text-muted mt-2" style="font-size:.75rem">PNG, JPG, GIF, WebP — Max 2MB</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-avocado px-4">
                            <i class="ri-save-3-line"></i> Save Pharmacy Information
                        </button>
                    </div>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════════════
                 TAB 2 : SYSTEM SETTINGS
                 ════════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="systemPane" role="tabpanel" aria-labelledby="system-tab">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="form_action" value="save_system">

                    <div class="row g-4">
                        <!-- General -->
                        <div class="col-lg-6">
                            <div class="settings-card">
                                <div class="card-header">
                                    <h6><i class="ri-settings-3-line"></i> General Settings</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="section-title">Localization &amp; Currency</div>
                                    <div class="row g-3 mb-4">
                                        <div class="col-sm-6">
                                            <label class="form-label">Currency</label>
                                            <select name="currency" class="form-select">
                                                <option value="TZS" <?= s('currency','TZS')==='TZS'?'selected':'' ?>>TZS — Tanzanian Shilling</option>
                                                <option value="USD" <?= s('currency')==='USD'?'selected':'' ?>>USD — US Dollar</option>
                                                <option value="KES" <?= s('currency')==='KES'?'selected':'' ?>>KES — Kenyan Shilling</option>
                                                <option value="UGX" <?= s('currency')==='UGX'?'selected':'' ?>>UGX — Ugandan Shilling</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label">Date Format</label>
                                            <select name="date_format" class="form-select">
                                                <option value="Y-m-d"   <?= s('date_format','Y-m-d')==='Y-m-d'?'selected':'' ?>>YYYY-MM-DD</option>
                                                <option value="d/m/Y"   <?= s('date_format')==='d/m/Y'?'selected':'' ?>>DD/MM/YYYY</option>
                                                <option value="m/d/Y"   <?= s('date_format')==='m/d/Y'?'selected':'' ?>>MM/DD/YYYY</option>
                                                <option value="d-M-Y"   <?= s('date_format')==='d-M-Y'?'selected':'' ?>>DD-Mon-YYYY</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="section-title">Tax &amp; Thresholds</div>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label class="form-label">Tax Rate (%)</label>
                                            <div class="input-group">
                                                <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" max="100" value="<?= htmlspecialchars(s('tax_rate','0')) ?>">
                                                <span class="input-group-text"><i class="ri-percent-line"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label">Low Stock Threshold</label>
                                            <div class="input-group">
                                                <input type="number" name="low_stock_threshold" class="form-control" min="1" value="<?= htmlspecialchars(s('low_stock_threshold','10')) ?>">
                                                <span class="input-group-text"><i class="ri-arrow-down-line"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label">Expiry Warning (Days)</label>
                                            <div class="input-group">
                                                <input type="number" name="expiry_warning_days" class="form-control" min="1" value="<?= htmlspecialchars(s('expiry_warning_days','30')) ?>">
                                                <span class="input-group-text"><i class="ri-calendar-close-line"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label">Invoice Prefix</label>
                                            <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars(s('invoice_prefix','INV')) ?>" placeholder="INV">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Appearance & Receipt -->
                        <div class="col-lg-6">
                            <div class="settings-card mb-4">
                                <div class="card-header">
                                    <h6><i class="ri-palette-line"></i> Appearance</h6>
                                </div>
                                <div class="card-body p-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="dark_mode_default" id="darkModeSwitch" value="1" <?= s('dark_mode_default')==='1'?'checked':'' ?>>
                                        <label class="form-check-label" for="darkModeSwitch">
                                            <i class="ri-moon-fill"></i> Dark Mode Default
                                        </label>
                                    </div>
                                    <div class="text-muted" style="font-size:.78rem; margin-left:2.5rem">Enable dark mode by default for all users</div>
                                </div>
                            </div>

                            <div class="settings-card">
                                <div class="card-header">
                                    <h6><i class="ri-file-text-line"></i> Receipt Settings</h6>
                                </div>
                                <div class="card-body p-4">
                                    <label class="form-label">Receipt Footer Text</label>
                                    <textarea name="receipt_footer" class="form-control" rows="4" placeholder="Thank you for choosing MedWell Pharmacy!"><?= htmlspecialchars(s('receipt_footer', 'Thank you for choosing MedWell Pharmacy! Your health is our priority.')) ?></textarea>
                                    <div class="text-muted mt-1" style="font-size:.75rem">This text appears at the bottom of printed receipts</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-avocado px-4">
                            <i class="ri-save-3-line"></i> Save System Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════════════
                 TAB 3 : USER MANAGEMENT
                 ════════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="usersPane" role="tabpanel" aria-labelledby="users-tab">
                <div class="settings-card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="mb-0"><i class="ri-group-line"></i> System Users</h6>
                        <button class="btn btn-avocado btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUser()">
                            <i class="ri-user-add-line"></i> Add User
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-hover align-middle mb-0" style="font-size:.875rem">
                                <thead style="background:var(--avocado-50)">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($allUsers)): ?>
                                        <?php foreach ($allUsers as $idx => $u): ?>
                                        <tr>
                                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:34px;height:34px;background:var(--avocado-100);color:var(--avocado-800);font-weight:700;font-size:.75rem">
                                                        <?= strtoupper(substr($u['full_name'] ?? $u['username'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($u['full_name'] ?? $u['username']) ?></div>
                                                        <div class="text-muted" style="font-size:.75rem">@<?= htmlspecialchars($u['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($u['email']) ?></td>
                                            <td>
                                                <?php
                                                    $role = $u['role'] ?? 'pharmacist';
                                                    $badgeClass = match($role) {
                                                        'admin'      => 'badge-role-admin',
                                                        'pharmacist' => 'badge-role-pharmacist',
                                                        'cashier'    => 'badge-role-cashier',
                                                        default      => 'bg-secondary'
                                                    };
                                                ?>
                                                <span class="badge rounded-pill <?= $badgeClass ?>"><?= ucfirst($role) ?></span>
                                            </td>
                                            <td>
                                                <?php $isActive = !empty($u['is_active']); ?>
                                                <span class="badge rounded-pill <?= $isActive ? 'badge-status-active' : 'badge-status-inactive' ?>">
                                                    <i class="ri-<?= $isActive ? 'checkbox-blank-circle-fill' : 'close-circle-line' ?>"></i>
                                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td class="text-muted">
                                                <?= !empty($u['last_login']) ? date('M d, Y h:i A', strtotime($u['last_login'])) : '<em>Never</em>' ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="action-btn action-btn-edit" title="Edit User" onclick="openEditUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)">
                                                        <i class="ri-pencil-line"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline" onsubmit="return confirm('Toggle user status?')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="form_action" value="toggle_user">
                                                        <input type="hidden" name="toggle_user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="action-btn action-btn-toggle" title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
                                                            <i class="ri-<?= $isActive ? 'user-unfollow-line' : 'user-follow-line' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <?php if (intval($u['id']) !== intval($_SESSION['user_id'] ?? 0)): ?>
                                                    <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <input type="hidden" name="form_action" value="delete_user">
                                                        <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="action-btn action-btn-delete" title="Delete User">
                                                            <i class="ri-delete-bin-6-line"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <button class="action-btn action-btn-delete" disabled title="Cannot delete yourself" style="opacity:.4">
                                                        <i class="ri-delete-bin-6-line"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="ri-user-line"></i> No users found</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════
                 TAB 4 : PASSWORD UPDATE
                 ════════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="passwordPane" role="tabpanel" aria-labelledby="password-tab">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="settings-card">
                            <div class="card-header">
                                <h6><i class="ri-lock-password-line"></i> Update Password</h6>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" autocomplete="off" id="passwordForm">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="form_action" value="update_password">

                                    <div class="mb-3">
                                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-lock-line"></i></span>
                                            <input type="password" name="current_password" id="currentPassword" class="form-control" required placeholder="Enter current password">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword', this)">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <hr class="my-4" style="border-color:var(--avocado-100)">

                                    <div class="mb-3">
                                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                                            <input type="password" name="new_password" id="newPassword" class="form-control" required placeholder="Min. 8 characters" oninput="checkStrength(this.value)">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword', this)">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                        <!-- Strength Indicator -->
                                        <div class="mt-2">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <div class="flex-grow-1 rounded" style="height:6px;background:#e5e7eb">
                                                    <div class="strength-bar" id="strengthBar"></div>
                                                </div>
                                                <span id="strengthText" class="fw-semibold" style="font-size:.75rem"></span>
                                            </div>
                                            <div id="strengthTips" class="text-muted" style="font-size:.72rem"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-lock-password-line"></i></span>
                                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required placeholder="Re-enter new password" oninput="checkMatch()">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', this)">
                                                <i class="ri-eye-off-line"></i>
                                            </button>
                                        </div>
                                        <div id="matchFeedback" class="mt-1" style="font-size:.75rem"></div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-avocado w-100">
                                            <i class="ri-shield-check-line"></i> Update Password
                                        </button>
                                    </div>
                                </form>

                                <!-- Password Tips Card -->
                                <div class="mt-4 p-3 rounded" style="background:var(--avocado-50);border:1px solid var(--avocado-200)">
                                    <div class="fw-semibold mb-2" style="color:var(--avocado-800);font-size:.82rem">
                                        <i class="ri-lightbulb-flash-line"></i> Password Tips
                                    </div>
                                    <ul class="mb-0 ps-3" style="font-size:.78rem;color:#374151">
                                        <li>At least <strong>8 characters</strong> long</li>
                                        <li>Mix of <strong>uppercase &amp; lowercase</strong> letters</li>
                                        <li>Include at least one <strong>number</strong></li>
                                        <li>Include at least one <strong>special character</strong> (!@#$%)</li>
                                        <li>Avoid using your name or common words</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /tab-content -->
    </div><!-- /p-3 p-lg-4 -->
</div><!-- /main-content -->


<!-- ╔══════════════════════════════════════════════════════════════════════╗
     ║  USER MODAL (Add / Edit)                                           ║
     ╚══════════════════════════════════════════════════════════════════════╝ -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="userForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="form_action" value="save_user">
                <input type="hidden" name="user_id" id="modalUserId" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="ri-user-add-fill"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="modalUsername" class="form-control" required placeholder="e.g. jdoe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="modalEmail" class="form-control" required placeholder="e.g. john@medwell.co.tz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="modalFullName" class="form-control" required placeholder="e.g. John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" id="modalPhone" class="form-control" placeholder="+255 700 000 000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger" id="passwordRequiredStar">*</span></label>
                            <input type="password" name="password" id="modalPassword" class="form-control" placeholder="Min. 8 characters">
                            <div class="form-text" id="passwordHint">Required for new users. Leave blank to keep unchanged when editing.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="modalRole" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="pharmacist" selected>Pharmacist</option>
                                <option value="cashier">Cashier</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="modalIsActive" value="1" checked>
                                <label class="form-check-label" for="modalIsActive">Active Account</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="ri-close-line"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-avocado">
                        <i class="ri-save-3-line"></i> <span id="modalSaveText">Create User</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ╔══════════════════════════════════════════════════════════════════════╗
     ║  SCRIPTS                                                           ║
     ╚══════════════════════════════════════════════════════════════════════╝ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
// ─── Sidebar Toggle ─────────────────────────────────────────────────────
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('show');
});

// ─── Logo Preview ───────────────────────────────────────────────────────
document.getElementById('logoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        const preview = document.getElementById('logoPreview');
        const existingImg = preview.querySelector('img');
        const placeholder = document.getElementById('logoPlaceholder');
        if (existingImg) {
            existingImg.src = ev.target.result;
        } else {
            if (placeholder) placeholder.remove();
            const img = document.createElement('img');
            img.src = ev.target.result;
            img.id = 'logoImg';
            preview.appendChild(img);
        }
    };
    reader.readAsDataURL(file);
});

// ─── User Modal: Add ────────────────────────────────────────────────────
function openAddUser() {
    document.getElementById('userForm').reset();
    document.getElementById('modalUserId').value = 0;
    document.getElementById('userModalLabel').innerHTML = '<i class="ri-user-add-fill"></i> Add New User';
    document.getElementById('modalSaveText').textContent = 'Create User';
    document.getElementById('modalPassword').required = true;
    document.getElementById('passwordRequiredStar').style.display = '';
    document.getElementById('passwordHint').textContent = 'Required for new users.';
    document.getElementById('modalIsActive').checked = true;
    document.getElementById('modalRole').value = 'pharmacist';
}

// ─── User Modal: Edit ───────────────────────────────────────────────────
function openEditUser(user) {
    document.getElementById('userForm').reset();
    document.getElementById('modalUserId').value = user.id;
    document.getElementById('modalUsername').value = user.username || '';
    document.getElementById('modalEmail').value = user.email || '';
    document.getElementById('modalFullName').value = user.full_name || '';
    document.getElementById('modalPhone').value = user.phone || '';
    document.getElementById('modalRole').value = user.role || 'pharmacist';
    document.getElementById('modalIsActive').checked = !!user.is_active;
    document.getElementById('modalPassword').required = false;
    document.getElementById('passwordRequiredStar').style.display = 'none';
    document.getElementById('passwordHint').textContent = 'Leave blank to keep current password unchanged.';
    document.getElementById('userModalLabel').innerHTML = '<i class="ri-user-edit-fill"></i> Edit User';
    document.getElementById('modalSaveText').textContent = 'Update User';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

// ─── DataTable ──────────────────────────────────────────────────────────
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#usersTable').DataTable({
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [-1] },
                { className: 'text-center', targets: [-1] }
            ],
            language: {
                search: '<i class="ri-search-line"></i>',
                searchPlaceholder: 'Search users...',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ users',
                paginate: {
                    previous: '<i class="ri-arrow-left-s-line"></i>',
                    next: '<i class="ri-arrow-right-s-line"></i>'
                }
            }
        });
    }
});

// ─── Password Strength Checker ──────────────────────────────────────────
function checkStrength(password) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const tips = document.getElementById('strengthTips');

    let score = 0;
    const checks = [];

    if (password.length >= 8) score++; else checks.push('At least 8 characters');
    if (/[a-z]/.test(password)) score++; else checks.push('A lowercase letter');
    if (/[A-Z]/.test(password)) score++; else checks.push('An uppercase letter');
    if (/[0-9]/.test(password)) score++; else checks.push('A number');
    if (/[^a-zA-Z0-9]/.test(password)) score++; else checks.push('A special character');

    bar.className = 'strength-bar';
    if (password.length === 0) {
        bar.style.width = '0%';
        text.textContent = '';
        tips.textContent = '';
    } else if (score <= 2) {
        bar.classList.add('strength-weak');
        text.textContent = 'Weak';
        text.style.color = '#EF4444';
    } else if (score <= 3) {
        bar.classList.add('strength-medium');
        text.textContent = 'Medium';
        text.style.color = '#F59E0B';
    } else {
        bar.classList.add('strength-strong');
        text.textContent = 'Strong';
        text.style.color = '#7CB342';
    }

    tips.textContent = checks.length > 0 && password.length > 0
        ? 'Missing: ' + checks.join(', ')
        : (score >= 5 ? 'Excellent password!' : '');

    checkMatch();
}

// ─── Password Match Checker ─────────────────────────────────────────────
function checkMatch() {
    const newPw  = document.getElementById('newPassword').value;
    const confPw = document.getElementById('confirmPassword').value;
    const feedback = document.getElementById('matchFeedback');

    if (confPw.length === 0) {
        feedback.textContent = '';
        return;
    }
    if (newPw === confPw) {
        feedback.innerHTML = '<i class="ri-checkbox-circle-fill" style="color:#7CB342"></i> <span style="color:#065F46">Passwords match</span>';
    } else {
        feedback.innerHTML = '<i class="ri-close-circle-fill" style="color:#EF4444"></i> <span style="color:#991B1B">Passwords do not match</span>';
    }
}

// ─── Toggle Password Visibility ─────────────────────────────────────────
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'ri-eye-line';
    } else {
        input.type = 'password';
        icon.className = 'ri-eye-off-line';
    }
}

// ─── Auto-dismiss flash alerts ──────────────────────────────────────────
document.querySelectorAll('.alert-flash').forEach(alert => {
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
        bsAlert.close();
    }, 5000);
});
</script>

</body>
</html>
