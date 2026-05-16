<?php
/**
 * MedWell Pharmacy - Add Supplier Page
 *
 * Premium form page for adding new suppliers with validation,
 * icons on each field, and CSRF protection.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'suppliers';
$pageTitle = 'Add Supplier';

$supplierObj = new Supplier();

// Handle form submission
$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('supplier_error', 'Invalid security token. Please try again.', 'error');
        redirect('/modules/suppliers/add.php');
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
        'is_active'      => 1,
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

    // Insert if no errors
    if (empty($errors)) {
        $newId = $supplierObj->create($formData);
        if ($newId) {
            regenerateCsrfToken();
            flashMessage('supplier_success', "Supplier \"{$formData['name']}\" has been added successfully.", 'success');
            redirect('/modules/suppliers/');
        } else {
            $errors['general'] = 'Failed to add supplier. Please try again.';
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .form-page-header .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .form-page-header .back-btn {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .form-page-header .back-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .form-page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .form-page-header .subtitle {
            font-size: 0.86rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Form Card */
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            max-width: 900px;
        }
        .form-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 28px;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-50), transparent);
        }
        .form-card-header .header-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .form-card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .form-card-header p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin: 2px 0 0;
        }

        /* Form Sections */
        .form-section {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border-color);
        }
        .form-section:last-of-type {
            border-bottom: none;
        }
        .form-section-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--primary-dark);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-title i {
            font-size: 1rem;
            color: var(--primary);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 24px;
        }
        .form-grid .full-width {
            grid-column: 1 / -1;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .form-group label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        .form-group .input-icon-wrapper {
            position: relative;
        }
        .form-group .input-icon-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
            pointer-events: none;
            transition: var(--transition);
        }
        .form-group .input-icon-wrapper input,
        .form-group .input-icon-wrapper select,
        .form-group .input-icon-wrapper textarea {
            padding-left: 38px;
        }
        .form-group .input-icon-wrapper input:focus ~ i,
        .form-group .input-icon-wrapper select:focus ~ i,
        .form-group .input-icon-wrapper textarea:focus ~ i {
            color: var(--primary);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            font-family: var(--font-sans);
            color: var(--text-primary);
            background: var(--bg-card);
            transition: var(--transition);
            outline: none;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }
        .form-group input.is-invalid,
        .form-group select.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group .invalid-feedback {
            font-size: 0.76rem;
            color: var(--danger);
            margin-top: 4px;
        }
        .form-group .form-hint {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Form Footer */
        .form-card-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            padding: 18px 28px;
            border-top: 1px solid var(--border-color);
            background: var(--primary-50);
        }

        /* Error Alert */
        .error-alert {
            background: rgba(231, 76, 60, 0.06);
            border: 1px solid rgba(231, 76, 60, 0.2);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e74c3c;
            font-size: 0.88rem;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
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
        <div class="form-page-header">
            <div class="header-left">
                <a href="/modules/suppliers/" class="back-btn" title="Back to Suppliers">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h2>Add Supplier</h2>
                    <div class="subtitle">Add a new supplier to your network</div>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="header-icon">
                    <i class="ri-truck-line"></i>
                </div>
                <div>
                    <h3>New Supplier Details</h3>
                    <p>Fill in the information below to add a new supplier</p>
                </div>
            </div>

            <form method="POST" action="" id="addSupplierForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <?php if (!empty($errors['general'])): ?>
                <div class="error-alert">
                    <i class="ri-error-warning-line" style="font-size: 1.1rem;"></i>
                    <?= sanitize($errors['general']) ?>
                </div>
                <?php endif; ?>

                <!-- Basic Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="ri-information-line"></i> Basic Information
                    </div>
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
                    <div class="form-section-title">
                        <i class="ri-map-pin-line"></i> Address Information
                    </div>
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

                <!-- Form Footer -->
                <div class="form-card-footer">
                    <a href="/modules/suppliers/" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-close-line"></i> Cancel
                    </a>
                    <button type="reset" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-refresh-line"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ri-check-line"></i> Save Supplier
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<script src="/assets/js/app.js"></script>
<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addSupplierForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const name = form.querySelector('input[name="name"]');
            const email = form.querySelector('input[name="email"]');
            let valid = true;

            if (!name.value.trim()) {
                name.classList.add('is-invalid');
                valid = false;
            } else {
                name.classList.remove('is-invalid');
            }
            if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                email.classList.add('is-invalid');
                valid = false;
            } else {
                email.classList.remove('is-invalid');
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    }
});
</script>
</body>
</html>
