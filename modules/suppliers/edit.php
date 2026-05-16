<?php
/**
 * MedWell Pharmacy - Edit Supplier Page
 *
 * Edit existing supplier with pre-populated form fields,
 * validation, and CSRF protection.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'suppliers';
$pageTitle = 'Edit Supplier';

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

$errors = [];
$formData = $supplier;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('supplier_error', 'Invalid security token. Please try again.', 'error');
        redirect('/modules/suppliers/edit.php?id=' . $id);
    }

    // Sanitize and collect form data
    $formData = [
        'name'           => trim($_POST['name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'city'           => trim($_POST['city'] ?? ''),
        'state'          => trim($_POST['state'] ?? ''),
        'zip_code'       => trim($_POST['zip_code'] ?? ''),
        'is_active'      => isset($_POST['is_active']) ? (int) $_POST['is_active'] : (int) $supplier['is_active'],
    ];

    // Validation
    if (empty($formData['name'])) {
        $errors['name'] = 'Supplier name is required.';
    }
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!empty($formData['phone']) && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $formData['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    // Update if no errors
    if (empty($errors)) {
        $result = $supplierObj->update($id, $formData);
        if ($result) {
            regenerateCsrfToken();
            flashMessage('supplier_success', "Supplier \"{$formData['name']}\" has been updated successfully.", 'success');
            redirect('/modules/suppliers/');
        } else {
            $errors['general'] = 'Failed to update supplier. Please try again.';
        }
    }
}
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .form-page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; flex-wrap: wrap; gap: 16px;
        }
        .form-page-header .header-left { display: flex; align-items: center; gap: 16px; }
        .form-page-header .back-btn {
            width: 42px; height: 42px; border-radius: var(--radius-md);
            border: 1px solid var(--border-color); background: var(--bg-card);
            color: var(--text-secondary); display: flex; align-items: center;
            justify-content: center; font-size: 1.2rem; cursor: pointer;
            transition: var(--transition); text-decoration: none;
        }
        .form-page-header .back-btn:hover {
            border-color: var(--primary); color: var(--primary); background: var(--primary-50);
        }
        .form-page-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
        .form-page-header .subtitle { font-size: 0.86rem; color: var(--text-muted); margin-top: 2px; }
        .form-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-md);
            overflow: hidden; max-width: 900px;
        }
        .form-card-header {
            display: flex; align-items: center; gap: 12px; padding: 20px 28px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-50), transparent);
        }
        .form-card-header .header-icon {
            width: 44px; height: 44px; border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .form-card-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
        .form-card-header p { font-size: 0.82rem; color: var(--text-muted); margin: 2px 0 0; }
        .form-section { padding: 24px 28px; border-bottom: 1px solid var(--border-color); }
        .form-section:last-of-type { border-bottom: none; }
        .form-section-title {
            font-size: 0.88rem; font-weight: 700; color: var(--primary-dark);
            text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
        }
        .form-section-title i { font-size: 1rem; color: var(--primary); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 24px; }
        .form-grid .full-width { grid-column: 1 / -1; }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block; font-size: 0.82rem; font-weight: 600;
            color: var(--text-primary); margin-bottom: 6px;
        }
        .form-group label .required { color: var(--danger); margin-left: 2px; }
        .form-group .input-icon-wrapper { position: relative; }
        .form-group .input-icon-wrapper i {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 0.9rem; pointer-events: none;
            transition: var(--transition);
        }
        .form-group .input-icon-wrapper input,
        .form-group .input-icon-wrapper select,
        .form-group .input-icon-wrapper textarea { padding-left: 38px; }
        .form-group .input-icon-wrapper input:focus ~ i,
        .form-group .input-icon-wrapper select:focus ~ i,
        .form-group .input-icon-wrapper textarea:focus ~ i { color: var(--primary); }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border-color);
            border-radius: var(--radius-sm); font-size: 0.88rem; font-family: var(--font-sans);
            color: var(--text-primary); background: var(--bg-card); transition: var(--transition); outline: none;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }
        .form-group input.is-invalid { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1); }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group .invalid-feedback { font-size: 0.76rem; color: var(--danger); margin-top: 4px; }
        .form-group .form-hint { font-size: 0.74rem; color: var(--text-muted); margin-top: 4px; }
        .form-card-footer {
            display: flex; align-items: center; justify-content: flex-end; gap: 12px;
            padding: 18px 28px; border-top: 1px solid var(--border-color); background: var(--primary-50);
        }
        .error-alert {
            background: rgba(231, 76, 60, 0.06); border: 1px solid rgba(231, 76, 60, 0.2);
            border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; color: #e74c3c; font-size: 0.88rem;
        }
        .supplier-id-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-50); color: var(--primary-dark);
            padding: 4px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;
        }
        /* Status Toggle */
        .status-toggle-wrapper {
            display: flex; align-items: center; gap: 12px; padding: 4px 0;
        }
        .status-toggle {
            position: relative; width: 48px; height: 26px; background: var(--border-color);
            border-radius: 13px; cursor: pointer; border: none; transition: var(--transition);
        }
        .status-toggle::after {
            content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px;
            background: #fff; border-radius: 50%; transition: var(--transition);
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .status-toggle.active { background: var(--primary); }
        .status-toggle.active::after { left: 25px; }
        .status-label {
            font-size: 0.84rem; font-weight: 600;
        }
        .status-label.active-label { color: var(--success); }
        .status-label.inactive-label { color: var(--text-muted); }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../../includes/templates/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/templates/header.php'; ?>

        <div class="form-page-header">
            <div class="header-left">
                <a href="/modules/suppliers/" class="back-btn" title="Back to Suppliers"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <h2>Edit Supplier</h2>
                    <div class="subtitle">Update supplier information <span class="supplier-id-badge">ID: #<?= $id ?></span></div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header">
                <div class="header-icon"><i class="ri-edit-line"></i></div>
                <div>
                    <h3>Edit: <?= sanitize($supplier['name']) ?></h3>
                    <p>Modify the fields you want to update</p>
                </div>
            </div>

            <form method="POST" action="" id="editSupplierForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="is_active" id="isActiveInput" value="<?= (int) ($formData['is_active'] ?? 1) ?>">

                <?php if (!empty($errors['general'])): ?>
                <div class="error-alert"><i class="ri-error-warning-line" style="font-size: 1.1rem;"></i><?= sanitize($errors['general']) ?></div>
                <?php endif; ?>

                <!-- Basic Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-information-line"></i> Basic Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Supplier Name <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="name" value="<?= sanitize($formData['name'] ?? '') ?>" placeholder="e.g. MedPharm Distributors" required class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>">
                                <i class="ri-building-line"></i>
                            </div>
                            <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?= $errors['name'] ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Contact Person</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="contact_person" value="<?= sanitize($formData['contact_person'] ?? '') ?>" placeholder="e.g. John Smith">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-icon-wrapper">
                                <input type="email" name="email" value="<?= sanitize($formData['email'] ?? '') ?>" placeholder="e.g. contact@medpharm.com" class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>">
                                <i class="ri-mail-line"></i>
                            </div>
                            <?php if (!empty($errors['email'])): ?><div class="invalid-feedback"><?= $errors['email'] ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <div class="input-icon-wrapper">
                                <input type="tel" name="phone" value="<?= sanitize($formData['phone'] ?? '') ?>" placeholder="e.g. +255 123 456 789" class="<?= !empty($errors['phone']) ? 'is-invalid' : '' ?>">
                                <i class="ri-phone-line"></i>
                            </div>
                            <?php if (!empty($errors['phone'])): ?><div class="invalid-feedback"><?= $errors['phone'] ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-map-pin-line"></i> Address Information</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Street Address</label>
                            <div class="input-icon-wrapper">
                                <textarea name="address" placeholder="Enter full street address" rows="3"><?= sanitize($formData['address'] ?? '') ?></textarea>
                                <i class="ri-road-map-line" style="top: 14px; transform: none;"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="city" value="<?= sanitize($formData['city'] ?? '') ?>" placeholder="e.g. Dar es Salaam">
                                <i class="ri-building-2-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>State / Region</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="state" value="<?= sanitize($formData['state'] ?? '') ?>" placeholder="e.g. Dar es Salaam Region">
                                <i class="ri-map-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Zip / Postal Code</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="zip_code" value="<?= sanitize($formData['zip_code'] ?? '') ?>" placeholder="e.g. 12345">
                                <i class="ri-hashtag"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-toggle-line"></i> Supplier Status</div>
                    <div class="status-toggle-wrapper">
                        <button type="button" class="status-toggle <?= (int) ($formData['is_active'] ?? 1) ? 'active' : '' ?>" id="statusToggle" onclick="toggleStatus()"></button>
                        <span class="status-label <?= (int) ($formData['is_active'] ?? 1) ? 'active-label' : 'inactive-label' ?>" id="statusLabel">
                            <?= (int) ($formData['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>

                <div class="form-card-footer">
                    <a href="/modules/suppliers/" class="btn btn-outline-secondary btn-sm"><i class="ri-close-line"></i> Cancel</a>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="ri-check-line"></i> Update Supplier</button>
                </div>
            </form>
        </div>

        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>
    </main>
</div>
<script src="/assets/js/app.js"></script>
<script>
function toggleStatus() {
    const toggle = document.getElementById('statusToggle');
    const label = document.getElementById('statusLabel');
    const input = document.getElementById('isActiveInput');
    const isActive = toggle.classList.contains('active');

    if (isActive) {
        toggle.classList.remove('active');
        label.textContent = 'Inactive';
        label.className = 'status-label inactive-label';
        input.value = '0';
    } else {
        toggle.classList.add('active');
        label.textContent = 'Active';
        label.className = 'status-label active-label';
        input.value = '1';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editSupplierForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const name = form.querySelector('input[name="name"]');
            const email = form.querySelector('input[name="email"]');
            let valid = true;
            if (!name.value.trim()) { name.classList.add('is-invalid'); valid = false; } else { name.classList.remove('is-invalid'); }
            if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { email.classList.add('is-invalid'); valid = false; } else { email.classList.remove('is-invalid'); }
            if (!valid) e.preventDefault();
        });
    }
});
</script>
</body>
</html>
