<?php
/**
 * MedWell Pharmacy - Edit Medicine Page
 *
 * Edit existing medicine with pre-populated form fields,
 * validation, and CSRF protection.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'medicines';
$pageTitle = 'Edit Medicine';

$medicineObj = new Medicine();
$categories = $medicineObj->getCategories();

// Fetch suppliers
$supplierObj = new Supplier();
$suppliers = $supplierObj->getAll();

// Load medicine by ID
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('medicine_error', 'Invalid medicine ID.', 'error');
    redirect('/modules/medicines/');
}

$medicine = $medicineObj->getById($id);
if (!$medicine) {
    flashMessage('medicine_error', 'Medicine not found.', 'error');
    redirect('/modules/medicines/');
}

$errors = [];
$formData = $medicine;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('medicine_error', 'Invalid security token. Please try again.', 'error');
        redirect('/modules/medicines/edit.php?id=' . $id);
    }

    // Sanitize and collect form data
    $formData = [
        'name'             => trim($_POST['name'] ?? ''),
        'generic_name'     => trim($_POST['generic_name'] ?? ''),
        'brand'            => trim($_POST['brand'] ?? ''),
        'category_id'      => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
        'batch_number'     => trim($_POST['batch_number'] ?? ''),
        'barcode'          => trim($_POST['barcode'] ?? ''),
        'description'      => trim($_POST['description'] ?? ''),
        'unit'             => trim($_POST['unit'] ?? 'tablet'),
        'price'            => !empty($_POST['price']) ? (float) $_POST['price'] : null,
        'cost_price'       => !empty($_POST['cost_price']) ? (float) $_POST['cost_price'] : null,
        'quantity'         => isset($_POST['quantity']) ? (int) $_POST['quantity'] : (int) $medicine['quantity'],
        'min_stock_level'  => !empty($_POST['min_stock_level']) ? (int) $_POST['min_stock_level'] : 10,
        'supplier_id'      => !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null,
        'manufacture_date' => !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null,
        'expiry_date'      => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
    ];

    // Validation
    if (empty($formData['name'])) {
        $errors['name'] = 'Medicine name is required.';
    }
    if ($formData['price'] === null || $formData['price'] < 0) {
        $errors['price'] = 'Valid selling price is required.';
    }
    if ($formData['cost_price'] !== null && $formData['cost_price'] < 0) {
        $errors['cost_price'] = 'Cost price cannot be negative.';
    }
    if (!empty($formData['expiry_date']) && !empty($formData['manufacture_date'])) {
        if ($formData['expiry_date'] <= $formData['manufacture_date']) {
            $errors['expiry_date'] = 'Expiry date must be after manufacture date.';
        }
    }

    // Update if no errors
    if (empty($errors)) {
        $result = $medicineObj->update($id, $formData);
        if ($result) {
            regenerateCsrfToken();
            flashMessage('medicine_success', "Medicine \"{$formData['name']}\" has been updated successfully.", 'success');
            redirect('/modules/medicines/');
        } else {
            $errors['general'] = 'Failed to update medicine. Please try again.';
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
        .form-group .input-icon-wrapper select:focus ~ i { color: var(--primary); }
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
        .medicine-id-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--primary-50); color: var(--primary-dark);
            padding: 4px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;
        }
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
                <a href="/modules/medicines/" class="back-btn" title="Back to Medicines"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <h2>Edit Medicine</h2>
                    <div class="subtitle">Update medicine information <span class="medicine-id-badge">ID: #<?= $id ?></span></div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header">
                <div class="header-icon"><i class="ri-edit-line"></i></div>
                <div>
                    <h3>Edit: <?= sanitize($medicine['name']) ?></h3>
                    <p>Modify the fields you want to update</p>
                </div>
            </div>

            <form method="POST" action="" id="editMedicineForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <?php if (!empty($errors['general'])): ?>
                <div class="error-alert"><i class="ri-error-warning-line" style="font-size: 1.1rem;"></i><?= sanitize($errors['general']) ?></div>
                <?php endif; ?>

                <!-- Basic Information -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-information-line"></i> Basic Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Medicine Name <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="name" value="<?= sanitize($formData['name'] ?? '') ?>" placeholder="e.g. Amoxicillin 500mg" required class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>">
                                <i class="ri-capsule-line"></i>
                            </div>
                            <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?= $errors['name'] ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Generic Name</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="generic_name" value="<?= sanitize($formData['generic_name'] ?? '') ?>" placeholder="e.g. Amoxicillin">
                                <i class="ri-flask-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Brand</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="brand" value="<?= sanitize($formData['brand'] ?? '') ?>" placeholder="e.g. GlaxoSmithKline">
                                <i class="ri-price-tag-3-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <div class="input-icon-wrapper">
                                <select name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($formData['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="ri-folder-line"></i>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Description</label>
                            <div class="input-icon-wrapper">
                                <textarea name="description" placeholder="Enter medicine description, dosage instructions, etc."><?= sanitize($formData['description'] ?? '') ?></textarea>
                                <i class="ri-file-text-line" style="top: 14px; transform: none;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batch & Identification -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-barcode-line"></i> Batch & Identification</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Batch Number</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="batch_number" value="<?= sanitize($formData['batch_number'] ?? '') ?>" placeholder="e.g. BN-2024-001">
                                <i class="ri-hashtag"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Barcode</label>
                            <div class="input-icon-wrapper">
                                <input type="text" name="barcode" value="<?= sanitize($formData['barcode'] ?? '') ?>" placeholder="e.g. 8901234567890">
                                <i class="ri-barcode-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Unit <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <select name="unit">
                                    <?php
                                    $units = ['tablet' => 'Tablet', 'capsule' => 'Capsule', 'bottle' => 'Bottle', 'syrup' => 'Syrup', 'injection' => 'Injection', 'cream' => 'Cream', 'drops' => 'Drops', 'inhaler' => 'Inhaler', 'other' => 'Other'];
                                    $selectedUnit = $formData['unit'] ?? 'tablet';
                                    foreach ($units as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $selectedUnit === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="ri-stack-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Supplier</label>
                            <div class="input-icon-wrapper">
                                <select name="supplier_id">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>" <?= ($formData['supplier_id'] ?? '') == $sup['id'] ? 'selected' : '' ?>><?= sanitize($sup['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="ri-truck-line"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing & Stock -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-money-dollar-circle-line"></i> Pricing & Stock</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Selling Price <span class="required">*</span></label>
                            <div class="input-icon-wrapper">
                                <input type="number" name="price" value="<?= $formData['price'] ?? '' ?>" step="0.01" min="0" required class="<?= !empty($errors['price']) ? 'is-invalid' : '' ?>">
                                <i class="ri-money-dollar-circle-line"></i>
                            </div>
                            <?php if (!empty($errors['price'])): ?><div class="invalid-feedback"><?= $errors['price'] ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Cost Price</label>
                            <div class="input-icon-wrapper">
                                <input type="number" name="cost_price" value="<?= $formData['cost_price'] ?? '' ?>" step="0.01" min="0" class="<?= !empty($errors['cost_price']) ? 'is-invalid' : '' ?>">
                                <i class="ri-bank-card-line"></i>
                            </div>
                            <?php if (!empty($errors['cost_price'])): ?><div class="invalid-feedback"><?= $errors['cost_price'] ?></div><?php endif; ?>
                            <div class="form-hint">Purchase price from supplier</div>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <div class="input-icon-wrapper">
                                <input type="number" name="quantity" value="<?= $formData['quantity'] ?? 0 ?>" min="0">
                                <i class="ri-archive-line"></i>
                            </div>
                            <div class="form-hint">Use "Adjust Stock" to log changes properly</div>
                        </div>
                        <div class="form-group">
                            <label>Min Stock Level</label>
                            <div class="input-icon-wrapper">
                                <input type="number" name="min_stock_level" value="<?= $formData['min_stock_level'] ?? 10 ?>" min="0">
                                <i class="ri-alarm-warning-line"></i>
                            </div>
                            <div class="form-hint">Alert when stock falls below this level</div>
                        </div>
                    </div>
                </div>

                <!-- Dates -->
                <div class="form-section">
                    <div class="form-section-title"><i class="ri-calendar-line"></i> Manufacture & Expiry Dates</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Manufacture Date</label>
                            <div class="input-icon-wrapper">
                                <input type="date" name="manufacture_date" value="<?= $formData['manufacture_date'] ?? '' ?>">
                                <i class="ri-calendar-2-line"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <div class="input-icon-wrapper">
                                <input type="date" name="expiry_date" value="<?= $formData['expiry_date'] ?? '' ?>" class="<?= !empty($errors['expiry_date']) ? 'is-invalid' : '' ?>">
                                <i class="ri-calendar-close-line"></i>
                            </div>
                            <?php if (!empty($errors['expiry_date'])): ?><div class="invalid-feedback"><?= $errors['expiry_date'] ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-card-footer">
                    <a href="/modules/medicines/" class="btn btn-outline-secondary btn-sm"><i class="ri-close-line"></i> Cancel</a>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="ri-check-line"></i> Update Medicine</button>
                </div>
            </form>
        </div>

        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>
    </main>
</div>
<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editMedicineForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const name = form.querySelector('input[name="name"]');
            const price = form.querySelector('input[name="price"]');
            let valid = true;
            if (!name.value.trim()) { name.classList.add('is-invalid'); valid = false; } else { name.classList.remove('is-invalid'); }
            if (!price.value || parseFloat(price.value) < 0) { price.classList.add('is-invalid'); valid = false; } else { price.classList.remove('is-invalid'); }
            if (!valid) e.preventDefault();
        });
    }
});
</script>
</body>
</html>
