<?php
/**
 * MedWell Pharmacy - Medicine Categories Management Page
 *
 * CRUD operations for medicine categories with DataTable listing,
 * add/edit modals, and delete confirmation.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'categories';
$pageTitle = 'Medicine Categories';

$medicineObj = new Medicine();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('category_error', 'Invalid security token. Please try again.', 'error');
        redirect('/modules/medicines/categories.php');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                flashMessage('category_error', 'Category name is required.', 'error');
                redirect('/modules/medicines/categories.php');
            }

            $result = $medicineObj->createCategory(['name' => $name, 'description' => $description]);
            if ($result) {
                regenerateCsrfToken();
                flashMessage('category_success', "Category \"{$name}\" has been created successfully.", 'success');
            } else {
                flashMessage('category_error', "Failed to create category. The name may already exist.", 'error');
            }
            redirect('/modules/medicines/categories.php');
            break;

        case 'update':
            $editId = (int) ($_POST['edit_id'] ?? 0);
            $name = trim($_POST['edit_name'] ?? '');
            $description = trim($_POST['edit_description'] ?? '');

            if ($editId <= 0 || empty($name)) {
                flashMessage('category_error', 'Invalid category data.', 'error');
                redirect('/modules/medicines/categories.php');
            }

            $result = $medicineObj->updateCategory($editId, ['name' => $name, 'description' => $description]);
            if ($result) {
                regenerateCsrfToken();
                flashMessage('category_success', "Category \"{$name}\" has been updated successfully.", 'success');
            } else {
                flashMessage('category_error', "Failed to update category. The name may already exist.", 'error');
            }
            redirect('/modules/medicines/categories.php');
            break;

        case 'delete':
            $deleteId = (int) ($_POST['delete_id'] ?? 0);
            if ($deleteId <= 0) {
                flashMessage('category_error', 'Invalid category ID.', 'error');
                redirect('/modules/medicines/categories.php');
            }

            // Check if medicines are linked
            $linkedCount = $medicineObj->countMedicinesByCategory($deleteId);
            if ($linkedCount > 0) {
                flashMessage('category_error', "Cannot delete category. It has {$linkedCount} medicine(s) linked to it. Please reassign medicines first.", 'error');
                redirect('/modules/medicines/categories.php');
            }

            $category = $medicineObj->getCategoryById($deleteId);
            $catName = $category['name'] ?? 'Unknown';

            $result = $medicineObj->deleteCategory($deleteId);
            if ($result) {
                regenerateCsrfToken();
                flashMessage('category_success', "Category \"{$catName}\" has been deleted successfully.", 'success');
            } else {
                flashMessage('category_error', "Failed to delete category.", 'error');
            }
            redirect('/modules/medicines/categories.php');
            break;
    }
}

// Fetch categories
$categories = $medicineObj->getCategories();
$totalCategories = count($categories);
$categoryStats = $medicineObj->getCategoryStats();

// Compute totals
$totalMedicines = 0;
$totalStockValue = 0;
foreach ($categoryStats as $stat) {
    $totalMedicines += (int) ($stat['medicine_count'] ?? 0);
    $totalStockValue += (float) ($stat['stock_value'] ?? 0);
}

// Flash messages
$flashSuccess = getFlashMessage('category_success');
$flashError = getFlashMessage('category_error');

// Pre-fill edit modal if edit_id in query
$editCategory = null;
if (!empty($_GET['edit_id'])) {
    $editCategory = $medicineObj->getCategoryById((int) $_GET['edit_id']);
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
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .page-header-custom {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; flex-wrap: wrap; gap: 16px;
        }
        .page-header-custom .header-left h2 {
            font-size: 1.6rem; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 10px;
        }
        .page-header-custom .header-left h2 i { color: var(--primary); font-size: 1.4rem; }
        .page-header-custom .header-left .subtitle {
            font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;
        }
        .page-header-custom .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

        /* Stats Row */
        .stats-row {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 20px;
        }

        /* Table Card */
        .table-card {
            background: var(--bg-card); border-radius: var(--radius-md);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;
        }
        .table-card .card-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 22px; border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap; gap: 12px;
        }
        .table-card .card-top .search-box {
            position: relative;
        }
        .table-card .card-top .search-box i {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 0.9rem;
        }
        .table-card .card-top .search-box input {
            padding: 8px 12px 8px 36px; border: 1px solid var(--border-color);
            border-radius: var(--radius-sm); font-size: 0.84rem; width: 260px;
            color: var(--text-primary); background: var(--bg-card); outline: none;
            transition: var(--transition); font-family: var(--font-sans);
        }
        .table-card .card-top .search-box input:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }

        /* DataTable Overrides */
        .dataTables_wrapper { padding: 0 !important; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 22px !important; font-size: 0.84rem; color: var(--text-secondary);
        }
        table.dataTable tbody td {
            vertical-align: middle; padding: 12px 16px !important; font-size: 0.86rem;
        }
        table.dataTable thead th {
            background: var(--primary-50) !important; color: var(--text-secondary) !important;
            font-weight: 600 !important; font-size: 0.78rem !important;
            text-transform: uppercase; letter-spacing: 0.04em; padding: 12px 16px !important;
            border-bottom: 2px solid var(--border-color) !important;
        }

        /* Action Buttons */
        .action-btns { display: flex; gap: 6px; align-items: center; }
        .action-btn {
            width: 32px; height: 32px; border-radius: 8px;
            border: 1px solid var(--border-color); background: var(--bg-card);
            color: var(--text-secondary); display: flex; align-items: center;
            justify-content: center; font-size: 0.9rem; cursor: pointer;
            transition: var(--transition); text-decoration: none;
        }
        .action-btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .action-btn.btn-edit:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-50); }
        .action-btn.btn-delete:hover { border-color: var(--danger); color: var(--danger); background: rgba(231,76,60,0.06); }

        /* Category Name Cell */
        .cat-name-cell { display: flex; align-items: center; gap: 10px; }
        .cat-color-dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }

        /* Medicines Count Badge */
        .med-count-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 6px;
            font-size: 0.78rem; font-weight: 600;
            background: var(--primary-50); color: var(--primary-dark);
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 2000; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: var(--transition); padding: 20px;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-dialog {
            background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            width: 100%; max-width: 500px; max-height: 85vh; overflow: hidden;
            display: flex; flex-direction: column; transform: scale(0.9) translateY(20px);
            transition: var(--transition);
        }
        .modal-overlay.active .modal-dialog { transform: scale(1) translateY(0); }
        .modal-header-custom {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px; border-bottom: 1px solid var(--border-color);
        }
        .modal-header-custom h3 { font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .modal-header-custom h3 i { color: var(--primary); }
        .modal-close {
            width: 32px; height: 32px; border-radius: var(--radius-sm); border: none;
            background: transparent; color: var(--text-muted); font-size: 1.2rem;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: var(--transition);
        }
        .modal-close:hover { background: var(--primary-50); color: var(--danger); }
        .modal-body-custom { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer-custom {
            display: flex; align-items: center; justify-content: flex-end; gap: 10px;
            padding: 16px 24px; border-top: 1px solid var(--border-color);
        }
        .form-group-modal { margin-bottom: 18px; }
        .form-group-modal label {
            display: block; font-size: 0.82rem; font-weight: 600;
            color: var(--text-primary); margin-bottom: 6px;
        }
        .form-group-modal label .required { color: var(--danger); margin-left: 2px; }
        .form-group-modal input,
        .form-group-modal textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border-color);
            border-radius: var(--radius-sm); font-size: 0.88rem; font-family: var(--font-sans);
            color: var(--text-primary); background: var(--bg-card); transition: var(--transition); outline: none;
        }
        .form-group-modal input:focus,
        .form-group-modal textarea:focus {
            border-color: var(--primary); box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }
        .form-group-modal textarea { min-height: 80px; resize: vertical; }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <?php include __DIR__ . '/../../includes/templates/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/templates/header.php'; ?>

        <!-- Page Header -->
        <div class="page-header-custom">
            <div class="header-left">
                <h2><i class="ri-folder-line"></i> Medicine Categories</h2>
                <div class="subtitle">Organize medicines by therapeutic categories</div>
            </div>
            <div class="header-actions">
                <a href="/modules/medicines/" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Back to Medicines
                </a>
                <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                    <i class="ri-add-line"></i> Add Category
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flashSuccess): ?>
        <div style="background: rgba(39,174,96,0.08); border: 1px solid rgba(39,174,96,0.2); border-radius: var(--radius-sm); padding: 12px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #27ae60; font-size: 0.88rem; font-weight: 500;">
            <i class="ri-check-line" style="font-size: 1.1rem;"></i>
            <?= sanitize($flashSuccess['message']) ?>
            <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #27ae60; cursor: pointer; font-size: 1rem;"><i class="ri-close-line"></i></button>
        </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
        <div style="background: rgba(231,76,60,0.08); border: 1px solid rgba(231,76,60,0.2); border-radius: var(--radius-sm); padding: 12px 18px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #e74c3c; font-size: 0.88rem; font-weight: 500;">
            <i class="ri-error-warning-line" style="font-size: 1.1rem;"></i>
            <?= sanitize($flashError['message']) ?>
            <button onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 1rem;"><i class="ri-close-line"></i></button>
        </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-primary"><i class="ri-folder-line"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Categories</div>
                    <div class="stat-value"><?= number_format($totalCategories) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-success"><i class="ri-medicine-bottle-line"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Categorized Medicines</div>
                    <div class="stat-value"><?= number_format($totalMedicines) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-info"><i class="ri-money-dollar-circle-line"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Stock Value</div>
                    <div class="stat-value"><?= formatCurrency($totalStockValue) ?></div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-card">
            <div class="card-top">
                <div class="search-box">
                    <i class="ri-search-line"></i>
                    <input type="text" id="categorySearch" placeholder="Search categories...">
                </div>
                <div style="font-size: 0.84rem; color: var(--text-muted);">
                    <strong><?= number_format($totalCategories) ?></strong> categories
                </div>
            </div>
            <div class="table-container">
                <table id="categoriesTable" class="data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Medicines Count</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php
                            // Color rotation for category dots
                            $colors = ['#7CB342', '#3498db', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e', '#2ecc71', '#e91e63'];
                            $colorIdx = 0;
                            ?>
                            <?php foreach ($categories as $cat):
                                $dotColor = $colors[$colorIdx % count($colors)];
                                $colorIdx++;
                                $medCount = (int) ($cat['medicine_count'] ?? 0);

                                // Find stock value from stats
                                $stockValue = 0;
                                foreach ($categoryStats as $stat) {
                                    if ((int) $stat['id'] === (int) $cat['id']) {
                                        $stockValue = (float) ($stat['stock_value'] ?? 0);
                                        break;
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="cat-name-cell">
                                        <span class="cat-color-dot" style="background: <?= $dotColor ?>;"></span>
                                        <strong style="color: var(--text-primary);"><?= sanitize($cat['name']) ?></strong>
                                    </div>
                                </td>
                                <td style="color: var(--text-secondary); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= sanitize($cat['description'] ?? 'No description') ?>
                                </td>
                                <td>
                                    <span class="med-count-badge">
                                        <i class="ri-medicine-bottle-line" style="font-size: 0.8rem;"></i>
                                        <?= number_format($medCount) ?> medicine<?= $medCount !== 1 ? 's' : '' ?>
                                    </span>
                                    <?php if ($stockValue > 0): ?>
                                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 4px;">
                                        Value: <?= formatCurrency($stockValue) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn btn-edit" title="Edit"
                                            onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES) ?>')">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button class="action-btn btn-delete" title="Delete"
                                            onclick="confirmDeleteCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>', <?= $medCount ?>)">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 48px 20px;">
                                    <div style="color: var(--text-muted);">
                                        <i class="ri-folder-line" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                                        <p style="font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">No categories found</p>
                                        <p style="font-size: 0.86rem;">Create your first medicine category.</p>
                                        <button class="btn btn-primary btn-sm" style="margin-top: 16px;" onclick="openAddModal()">
                                            <i class="ri-add-line"></i> Add Category
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>
    </main>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCategoryModal">
    <div class="modal-dialog">
        <div class="modal-header-custom">
            <h3><i class="ri-add-circle-line"></i> Add Category</h3>
            <button class="modal-close" onclick="closeAddModal()"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body-custom">
                <div class="form-group-modal">
                    <label>Category Name <span class="required">*</span></label>
                    <input type="text" name="name" placeholder="e.g. Antibiotics" required autofocus>
                </div>
                <div class="form-group-modal">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of this category..."></textarea>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="ri-check-line"></i> Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal-dialog">
        <div class="modal-header-custom">
            <h3><i class="ri-edit-line"></i> Edit Category</h3>
            <button class="modal-close" onclick="closeEditModal()"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="edit_id" id="edit_id" value="">
            <div class="modal-body-custom">
                <div class="form-group-modal">
                    <label>Category Name <span class="required">*</span></label>
                    <input type="text" name="edit_name" id="edit_name" placeholder="e.g. Antibiotics" required>
                </div>
                <div class="form-group-modal">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description" placeholder="Brief description of this category..."></textarea>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="ri-check-line"></i> Update Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteCategoryModal">
    <div class="modal-dialog">
        <div class="modal-header-custom">
            <h3><i class="ri-delete-bin-line" style="color: var(--danger);"></i> Delete Category</h3>
            <button class="modal-close" onclick="closeDeleteModal()"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-body-custom">
            <p style="color: var(--text-secondary); font-size: 0.92rem;">
                Are you sure you want to delete <strong id="deleteCatName" style="color: var(--text-primary);"></strong>?
            </p>
            <p id="deleteCatWarning" style="color: var(--danger); font-size: 0.84rem; margin-top: 8px; display: none;">
                <i class="ri-alarm-warning-line"></i> This category has linked medicines. Please reassign them first.
            </p>
            <p id="deleteCatSafe" style="color: var(--text-muted); font-size: 0.84rem; margin-top: 8px;">
                This action cannot be undone.
            </p>
        </div>
        <div class="modal-footer-custom">
            <button class="btn btn-outline-secondary btn-sm" onclick="closeDeleteModal()">Cancel</button>
            <form id="deleteCatForm" method="POST" action="" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="delete_id" id="delete_cat_id" value="">
                <button type="submit" id="deleteCatBtn" class="btn btn-danger btn-sm"><i class="ri-delete-bin-line"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
// Initialize DataTable
$(document).ready(function() {
    $('#categoriesTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [3] }
        ],
        language: {
            search: '',
            searchPlaceholder: 'Quick search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ categories',
            paginate: {
                previous: '<i class="ri-arrow-left-s-line"></i>',
                next: '<i class="ri-arrow-right-s-line"></i>'
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });
});

// Custom search
document.getElementById('categorySearch')?.addEventListener('input', function() {
    $('#categoriesTable').DataTable().search(this.value).draw();
});

// Add Modal
function openAddModal() {
    document.getElementById('addCategoryModal').classList.add('active');
}
function closeAddModal() {
    document.getElementById('addCategoryModal').classList.remove('active');
}

// Edit Modal
function openEditModal(id, name, description) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('editCategoryModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editCategoryModal').classList.remove('active');
}

// Delete Modal
function confirmDeleteCategory(id, name, medCount) {
    document.getElementById('delete_cat_id').value = id;
    document.getElementById('deleteCatName').textContent = name;

    if (medCount > 0) {
        document.getElementById('deleteCatWarning').style.display = 'block';
        document.getElementById('deleteCatSafe').style.display = 'none';
        document.getElementById('deleteCatBtn').disabled = true;
        document.getElementById('deleteCatBtn').style.opacity = '0.5';
    } else {
        document.getElementById('deleteCatWarning').style.display = 'none';
        document.getElementById('deleteCatSafe').style.display = 'block';
        document.getElementById('deleteCatBtn').disabled = false;
        document.getElementById('deleteCatBtn').style.opacity = '1';
    }

    document.getElementById('deleteCategoryModal').classList.add('active');
}
function closeDeleteModal() {
    document.getElementById('deleteCategoryModal').classList.remove('active');
}

<?php if ($editCategory): ?>
// Auto-open edit modal if edit_id in query
document.addEventListener('DOMContentLoaded', function() {
    openEditModal(
        <?= $editCategory['id'] ?>,
        '<?= htmlspecialchars($editCategory['name'], ENT_QUOTES) ?>',
        '<?= htmlspecialchars($editCategory['description'] ?? '', ENT_QUOTES) ?>'
    );
});
<?php endif; ?>
</script>
</body>
</html>
