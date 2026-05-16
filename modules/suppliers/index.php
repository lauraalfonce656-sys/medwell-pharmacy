<?php
/**
 * MedWell Pharmacy - Suppliers Listing Page
 *
 * Premium suppliers management page with DataTable, search,
 * stats cards, and status badges.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Supplier.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'suppliers';
$pageTitle = 'Suppliers';

// Fetch data
$supplierObj = new Supplier();
$search = trim($_GET['search'] ?? '');
$suppliers = $supplierObj->getAll($search, 100, 0);
$totalSuppliers = $supplierObj->getTotalSuppliers();

// Count active suppliers
$activeSuppliers = countTable('suppliers', ['is_active' => 1]);

// Count total medicines supplied
$totalMedicinesSupplied = countTable('medicines', ['is_active' => 1]);

// Build medicines count per supplier
$supplierMedCounts = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SELECT supplier_id, COUNT(*) AS cnt FROM medicines WHERE supplier_id IS NOT NULL GROUP BY supplier_id');
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $supplierMedCounts[(int) $row['supplier_id']] = (int) $row['cnt'];
    }
} catch (PDOException $e) {
    // Silently continue
}

// Flash messages
$flashSuccess = getFlashMessage('supplier_success');
$flashError = getFlashMessage('supplier_error');
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
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ── Suppliers Page Styles ── */
        .suppliers-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .suppliers-header .header-left h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .suppliers-header .header-left h2 i {
            color: var(--primary);
            font-size: 1.4rem;
        }
        .suppliers-header .header-left .subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .suppliers-header .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Search Bar */
        .search-section {
            margin-bottom: 20px;
        }
        .search-wrapper {
            position: relative;
            max-width: 480px;
        }
        .search-wrapper i.search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
        }
        .search-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-xl);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 0.92rem;
            font-weight: 400;
            transition: var(--transition);
            outline: none;
            font-family: var(--font-sans);
        }
        .search-wrapper input::placeholder {
            color: var(--text-muted);
        }
        .search-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(124, 179, 66, 0.12);
            background: var(--bg-card);
        }

        /* Stats Summary Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        /* Table Card */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .table-card .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .table-card .card-top .record-count {
            font-size: 0.84rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .table-card .card-top .record-count strong {
            color: var(--text-primary);
        }

        /* Data Table Overrides */
        .dataTables_wrapper {
            padding: 0 !important;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 22px !important;
            font-size: 0.84rem;
            color: var(--text-secondary);
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            margin-left: 8px;
            outline: none;
            transition: var(--transition);
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 179, 66, 0.15);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            margin: 0 2px !important;
            border: 1px solid var(--border-color) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-50) !important;
            color: var(--primary) !important;
            border-color: var(--primary) !important;
        }
        table.dataTable tbody td {
            vertical-align: middle;
            padding: 12px 16px !important;
            font-size: 0.86rem;
        }
        table.dataTable thead th {
            background: var(--primary-50) !important;
            color: var(--text-secondary) !important;
            font-weight: 600 !important;
            font-size: 0.78rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 16px !important;
            border-bottom: 2px solid var(--border-color) !important;
        }

        /* Supplier Name Cell */
        .supplier-name-cell {
            display: flex;
            flex-direction: column;
        }
        .supplier-name-cell .supplier-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.88rem;
        }
        .supplier-name-cell .supplier-contact {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Medicines Count Badge */
        .med-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 600;
            background: var(--primary-100);
            color: var(--primary-dark);
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        .action-btn.btn-view:hover {
            border-color: var(--info);
            color: var(--info);
            background: rgba(52, 152, 219, 0.06);
        }
        .action-btn.btn-edit:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
        .action-btn.btn-delete:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: rgba(231, 76, 60, 0.06);
        }

        /* City Badge */
        .city-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        .city-badge i {
            color: var(--primary);
            font-size: 0.8rem;
        }

        /* Email/Phone Links */
        .email-link, .phone-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.84rem;
            transition: var(--transition);
        }
        .email-link:hover {
            color: var(--primary);
        }
        .phone-link:hover {
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .suppliers-header { flex-direction: column; align-items: flex-start; }
            .search-wrapper { max-width: 100%; }
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
        <div class="suppliers-header">
            <div class="header-left">
                <h2><i class="ri-truck-line"></i> Suppliers</h2>
                <div class="subtitle">Manage your medicine suppliers and distributors</div>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline-secondary btn-sm" onclick="exportSuppliers()" title="Export">
                    <i class="ri-download-line"></i> Export
                </button>
                <a href="/modules/suppliers/add.php" class="btn btn-primary btn-sm">
                    <i class="ri-add-line"></i> Add Supplier
                </a>
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

        <!-- Quick Stats -->
        <div class="stats-row">
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-primary">
                    <i class="ri-truck-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Suppliers</div>
                    <div class="stat-value"><?= number_format($totalSuppliers) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-success">
                    <i class="ri-checkbox-circle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Active Suppliers</div>
                    <div class="stat-value"><?= number_format($activeSuppliers) ?></div>
                </div>
            </div>
            <div class="stat-card card-hover-lift">
                <div class="stat-icon icon-info">
                    <i class="ri-medicine-bottle-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Medicines Supplied</div>
                    <div class="stat-value"><?= number_format($totalMedicinesSupplied) ?></div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-section">
            <form method="GET" action="">
                <div class="search-wrapper">
                    <i class="ri-search-line search-icon"></i>
                    <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search suppliers by name, email, phone, or contact person...">
                </div>
            </form>
        </div>

        <!-- Data Table Card -->
        <div class="table-card">
            <div class="card-top">
                <div class="record-count">
                    Showing <strong><?= count($suppliers) ?></strong> of <strong><?= number_format($totalSuppliers) ?></strong> suppliers
                </div>
            </div>

            <div class="table-container">
                <table id="suppliersTable" class="data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Medicines</th>
                            <th>Status</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $sup):
                                $medCount = $supplierMedCounts[(int) $sup['id']] ?? 0;
                                $isActive = (int) ($sup['is_active'] ?? 1);

                                if ($isActive) {
                                    $statusClass = 'badge-success';
                                    $statusText = 'Active';
                                } else {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Inactive';
                                }
                            ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-muted); font-size: 0.82rem;"><?= $sup['id'] ?></td>
                                <td>
                                    <div class="supplier-name-cell">
                                        <span class="supplier-name"><?= sanitize($sup['name']) ?></span>
                                        <?php if (!empty($sup['address'])): ?>
                                        <span class="supplier-contact">
                                            <i class="ri-map-pin-2-line"></i> <?= sanitize(mb_substr($sup['address'], 0, 40)) ?><?= mb_strlen($sup['address']) > 40 ? '...' : '' ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="color: var(--text-secondary);">
                                    <?= sanitize($sup['contact_person'] ?? '—') ?>
                                </td>
                                <td>
                                    <?php if (!empty($sup['email'])): ?>
                                    <a href="mailto:<?= sanitize($sup['email']) ?>" class="email-link">
                                        <i class="ri-mail-line" style="font-size: 0.8rem; margin-right: 3px;"></i>
                                        <?= sanitize($sup['email']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sup['phone'])): ?>
                                    <a href="tel:<?= sanitize($sup['phone']) ?>" class="phone-link">
                                        <i class="ri-phone-line" style="font-size: 0.8rem; margin-right: 3px;"></i>
                                        <?= sanitize($sup['phone']) ?>
                                    </a>
                                    <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sup['city'])): ?>
                                    <span class="city-badge">
                                        <i class="ri-map-pin-line"></i> <?= sanitize($sup['city']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="med-count-badge">
                                        <i class="ri-medicine-bottle-line" style="font-size: 0.75rem;"></i>
                                        <?= $medCount ?>
                                    </span>
                                </td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="/modules/suppliers/view.php?id=<?= $sup['id'] ?>" class="action-btn btn-view" title="View">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="/modules/suppliers/edit.php?id=<?= $sup['id'] ?>" class="action-btn btn-edit" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <button class="action-btn btn-delete" title="Delete" onclick="deleteSupplier(<?= $sup['id'] ?>, '<?= htmlspecialchars($sup['name'], ENT_QUOTES) ?>')">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 48px 20px;">
                                    <div style="color: var(--text-muted);">
                                        <i class="ri-truck-line" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                                        <p style="font-size: 1rem; font-weight: 600; margin-bottom: 4px; color: var(--text-secondary);">No suppliers found</p>
                                        <p style="font-size: 0.86rem;">Try adjusting your search or add a new supplier.</p>
                                        <a href="/modules/suppliers/add.php" class="btn btn-primary btn-sm" style="margin-top: 16px;">
                                            <i class="ri-add-line"></i> Add Supplier
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><i class="ri-delete-bin-line" style="color: var(--danger);"></i> Delete Supplier</h3>
            <button class="modal-close" onclick="closeDeleteModal()"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-secondary); font-size: 0.92rem;">
                Are you sure you want to delete <strong id="deleteSupName" style="color: var(--text-primary);"></strong>?
            </p>
            <p style="color: var(--text-muted); font-size: 0.84rem; margin-top: 8px;">
                This supplier will be deactivated. Any medicines linked to this supplier will have their supplier reference removed.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary btn-sm" onclick="closeDeleteModal()">Cancel</button>
            <form id="deleteForm" method="POST" action="/modules/suppliers/delete.php" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="id" id="deleteSupId" value="">
                <button type="submit" class="btn btn-danger btn-sm"><i class="ri-delete-bin-line"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
// Initialize DataTable
$(document).ready(function() {
    $('#suppliersTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [8] },
            { searchable: false, targets: [0, 6, 7, 8] }
        ],
        language: {
            search: '<i class="ri-search-line"></i>',
            searchPlaceholder: 'Quick search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ suppliers',
            paginate: {
                previous: '<i class="ri-arrow-left-s-line"></i>',
                next: '<i class="ri-arrow-right-s-line"></i>'
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });
});

// Delete Supplier
function deleteSupplier(id, name) {
    document.getElementById('deleteSupId').value = id;
    document.getElementById('deleteSupName').textContent = name;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Export
function exportSuppliers() {
    window.location.href = '/modules/suppliers/?export=csv';
}
</script>
</body>
</html>
