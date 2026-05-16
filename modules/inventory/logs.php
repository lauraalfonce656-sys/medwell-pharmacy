<?php
/**
 * MedWell Pharmacy - Inventory Movement Logs
 *
 * Full audit trail of all inventory movements with filtering,
 * date range selection, type filtering, and export functionality.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Medicine.class.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$currentPage = 'inventory';

// Fetch all medicines for the medicine filter dropdown
$medicineObj = new Medicine();
$allMedicines = $medicineObj->getAll(['is_active' => 1], '', 5000, 0);

// Fetch inventory logs from database
$db = Database::getInstance()->getConnection();

// Build query with filters
$whereClauses = ['1=1'];
$params = [];

// Date range filter
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
if (!empty($dateFrom)) {
    $whereClauses[] = 'il.created_at >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if (!empty($dateTo)) {
    $whereClauses[] = 'il.created_at <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

// Type filter
$typeFilter = $_GET['type'] ?? '';
if (!empty($typeFilter) && in_array($typeFilter, ['in', 'out', 'adjustment', 'expired'], true)) {
    $whereClauses[] = 'il.type = :type_filter';
    $params[':type_filter'] = $typeFilter;
}

// Medicine filter
$medicineFilter = $_GET['medicine_id'] ?? '';
if (!empty($medicineFilter)) {
    $whereClauses[] = 'il.medicine_id = :medicine_filter';
    $params[':medicine_filter'] = (int) $medicineFilter;
}

$whereSQL = implode(' AND ', $whereClauses);

$sql = "SELECT il.*, m.name AS medicine_name, m.batch_number, u.full_name AS user_name, u.username
        FROM inventory_logs il
        LEFT JOIN medicines m ON il.medicine_id = m.id
        LEFT JOIN users u ON il.user_id = u.id
        WHERE {$whereSQL}
        ORDER BY il.created_at DESC
        LIMIT 5000";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>Inventory Logs - MedWell Pharmacy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .filter-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
            transition: var(--transition);
        }
        .filter-card:hover {
            box-shadow: var(--shadow-md);
        }
        .filter-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
        }
        .filter-card .card-header h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-card .card-header h4 i {
            color: var(--primary);
        }
        .filter-card .card-body {
            padding: 20px 22px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 16px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .table-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
        }
        .table-card:hover {
            box-shadow: var(--shadow-md);
        }
        .table-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }
        .table-card .card-header h4 {
            font-size: 1.02rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-card .card-header h4 i {
            color: var(--primary);
        }
        .card-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .table-card .card-body {
            padding: 0;
        }
        .table-container {
            overflow-x: auto;
        }

        /* DataTables override */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 12px 22px;
            font-size: 0.85rem;
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
        .dataTables_wrapper table.data-table { margin: 0; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            border: 1px solid var(--border-color) !important;
            margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
        }

        /* Type badges */
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .type-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .type-badge.type-in {
            background: rgba(39,174,96,0.1);
            color: #27ae60;
        }
        .type-badge.type-in::before { background: #27ae60; }
        .type-badge.type-out {
            background: rgba(243,156,18,0.1);
            color: #e67e22;
        }
        .type-badge.type-out::before { background: #f39c12; }
        .type-badge.type-adjustment {
            background: rgba(52,152,219,0.1);
            color: #2980b9;
        }
        .type-badge.type-adjustment::before { background: #3498db; }
        .type-badge.type-expired {
            background: rgba(231,76,60,0.1);
            color: #e74c3c;
        }
        .type-badge.type-expired::before { background: #e74c3c; }

        /* Change value */
        .change-value {
            font-weight: 700;
            font-size: 0.88rem;
        }
        .change-value.positive { color: var(--success); }
        .change-value.negative { color: var(--danger); }
        .change-value.neutral  { color: var(--info); }

        /* Footer */
        .app-footer {
            margin-top: 32px;
            padding: 18px 0;
            border-top: 1px solid var(--border-color);
        }
        .app-footer .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .app-footer .footer-content p { margin: 0; }
        .app-footer .footer-version { color: var(--text-muted); font-size: 0.76rem; }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: var(--text-muted);
            text-align: center;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3; }
        .empty-state p { font-size: 0.88rem; color: var(--text-muted); }

        @media (max-width: 992px) {
            .filter-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 576px) {
            .filter-row {
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
        <div class="page-header">
            <div>
                <h2><i class="ri-file-list-3-line" style="color: var(--primary); margin-right: 8px;"></i>Inventory Movement Logs</h2>
                <div class="breadcrumb">
                    <a href="/modules/dashboard/"><i class="ri-home-4-line"></i> Home</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <a href="/modules/inventory/">Inventory</a>
                    <span class="separator"><i class="ri-arrow-right-s-line"></i></span>
                    <span>Movement Logs</span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="/modules/inventory/adjust.php" class="btn btn-primary btn-sm">
                    <i class="ri-add-line"></i> Adjust Stock
                </a>
                <a href="/modules/inventory/" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Back
                </a>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             FILTER CARD
             ═══════════════════════════════════════════════════════════ -->
        <div class="filter-card">
            <div class="card-header" onclick="this.parentElement.querySelector('.card-body').classList.toggle('d-none')">
                <h4><i class="ri-filter-3-line"></i> Filter Logs</h4>
                <i class="ri-arrow-down-s-line" style="font-size:1.2rem; color:var(--text-muted); transition:var(--transition);"></i>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="dateFrom">Date From</label>
                            <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?= sanitize($dateFrom) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="dateTo">Date To</label>
                            <input type="date" class="form-control" id="dateTo" name="date_to" value="<?= sanitize($dateTo) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="typeFilter">Type</label>
                            <select class="form-control" id="typeFilter" name="type">
                                <option value="">All Types</option>
                                <option value="in" <?= $typeFilter === 'in' ? 'selected' : '' ?>>Stock In</option>
                                <option value="out" <?= $typeFilter === 'out' ? 'selected' : '' ?>>Stock Out</option>
                                <option value="adjustment" <?= $typeFilter === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                                <option value="expired" <?= $typeFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="medicineFilter">Medicine</label>
                            <select class="form-control" id="medicineFilter" name="medicine_id">
                                <option value="">All Medicines</option>
                                <?php foreach ($allMedicines as $med): ?>
                                <option value="<?= $med['id'] ?>" <?= (string)$medicineFilter === (string)$med['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($med['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:flex; gap:8px; padding-bottom:2px;">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="ri-filter-line"></i> Apply
                            </button>
                            <a href="/modules/inventory/logs.php" class="btn btn-outline-secondary btn-sm" title="Clear all filters">
                                <i class="ri-close-line"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             LOGS TABLE
             ═══════════════════════════════════════════════════════════ -->
        <div class="table-card">
            <div class="card-header">
                <h4><i class="ri-table-line"></i> Movement History</h4>
                <div class="card-header-actions">
                    <button class="btn btn-outline-secondary btn-sm" onclick="exportLogsCSV()" title="Export logs">
                        <i class="ri-download-line"></i> Export
                    </button>
                    <span style="font-size:0.82rem; color:var(--text-muted);">
                        <?= number_format(count($logs)) ?> records
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table" id="logsTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Medicine</th>
                                <th>Type</th>
                                <th>Qty Before</th>
                                <th>Qty After</th>
                                <th>Change</th>
                                <th>Reference</th>
                                <th>User</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $change = (int) $log['quantity_after'] - (int) $log['quantity_before'];
                                    $changeClass = $change > 0 ? 'positive' : ($change < 0 ? 'negative' : 'neutral');
                                    $changeText = ($change >= 0 ? '+' : '') . $change;

                                    $typeBadge = match($log['type']) {
                                        'in' => 'type-in',
                                        'out' => 'type-out',
                                        'adjustment' => 'type-adjustment',
                                        'expired' => 'type-expired',
                                        default => 'type-adjustment',
                                    };
                                    $typeLabel = match($log['type']) {
                                        'in' => 'Stock In',
                                        'out' => 'Stock Out',
                                        'adjustment' => 'Adjustment',
                                        'expired' => 'Expired',
                                        default => ucfirst($log['type']),
                                    };
                                    $typeIcon = match($log['type']) {
                                        'in' => 'ri-add-circle-line',
                                        'out' => 'ri-subtract-line',
                                        'adjustment' => 'ri-swap-line',
                                        'expired' => 'ri-delete-bin-line',
                                        default => 'ri-swap-line',
                                    };
                                    ?>
                                    <tr>
                                        <td style="font-size:0.82rem; color:var(--text-muted); white-space:nowrap;">
                                            <?= formatDateTime($log['created_at'] ?? '') ?>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:var(--text-primary);"><?= sanitize($log['medicine_name'] ?? 'Unknown') ?></div>
                                            <?php if (!empty($log['batch_number'])): ?>
                                            <div style="font-size:0.72rem; color:var(--text-muted);">Batch: <?= sanitize($log['batch_number']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="type-badge <?= $typeBadge ?>">
                                                <i class="<?= $typeIcon ?>" style="font-size:0.8rem;"></i>
                                                <?= $typeLabel ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:600; text-align:center;"><?= number_format((int) $log['quantity_before']) ?></td>
                                        <td style="font-weight:600; text-align:center;"><?= number_format((int) $log['quantity_after']) ?></td>
                                        <td>
                                            <span class="change-value <?= $changeClass ?>"><?= $changeText ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['reference_type'])): ?>
                                                <code style="background:var(--primary-50); color:var(--primary-dark); padding:2px 8px; border-radius:4px; font-size:0.78rem;">
                                                    <?= sanitize($log['reference_type']) ?>
                                                </code>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted); font-size:0.82rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.85rem; color:var(--text-secondary);">
                                            <?= sanitize($log['user_name'] ?? $log['username'] ?? 'System') ?>
                                        </td>
                                        <td style="font-size:0.82rem; color:var(--text-muted); max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= sanitize($log['notes'] ?? '') ?>">
                                            <?= sanitize($log['notes'] ?? '—') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <i class="ri-file-list-3-line"></i>
                                            <p>No inventory logs found<?php if (!empty($dateFrom) || !empty($dateTo) || !empty($typeFilter) || !empty($medicineFilter)): ?> with the current filters<?php endif; ?>.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <?php include __DIR__ . '/../../includes/templates/footer.php'; ?>

    </main>
</div>

<!-- DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
// ─── DataTable Init ────────────────────────────────────────
$('#logsTable').DataTable({
    responsive: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
    order: [[0, 'desc']], // Sort by date descending
    language: {
        search: '<i class="ri-search-line"></i>',
        searchPlaceholder: 'Search logs...',
        lengthMenu: 'Show _MENU_ entries',
        info: 'Showing _START_ to _END_ of _TOTAL_ entries',
        paginate: {
            previous: '<i class="ri-arrow-left-s-line"></i>',
            next: '<i class="ri-arrow-right-s-line"></i>'
        }
    },
    columnDefs: [
        { orderable: false, targets: [8] }
    ]
});

// ─── Export CSV ────────────────────────────────────────────
function exportLogsCSV() {
    const table = document.getElementById('logsTable');
    const rows = table.querySelectorAll('tbody tr');
    let csv = 'Date,Medicine,Batch,Type,Qty Before,Qty After,Change,Reference,User,Notes\n';

    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 9) return;

        const date = cells[0].textContent.trim();
        const medName = cells[1].querySelector('div')?.textContent.trim() || cells[1].textContent.trim();
        const batch = cells[1].querySelectorAll('div')[1]?.textContent.replace('Batch: ', '').trim() || '';
        const type = cells[2].textContent.trim();
        const qtyBefore = cells[3].textContent.trim();
        const qtyAfter = cells[4].textContent.trim();
        const change = cells[5].textContent.trim();
        const ref = cells[6].textContent.trim();
        const user = cells[7].textContent.trim();
        const notes = cells[8].textContent.trim();

        csv += `"${date}","${medName}","${batch}","${type}","${qtyBefore}","${qtyAfter}","${change}","${ref}","${user}","${notes}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `inventory_logs_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);

    if (typeof showToast === 'function') {
        showToast({ type: 'success', title: 'Export Complete', message: 'Inventory logs exported as CSV', icon: 'ri-download-line', bgColor: '#7CB342' });
    }
}

// ─── Set default date range (last 30 days) if no filters ───
<?php if (empty($dateFrom) && empty($dateTo)): ?>
// Auto-set date from to 30 days ago
const thirtyDaysAgo = new Date();
thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
// document.getElementById('dateFrom').value = thirtyDaysAgo.toISOString().slice(0,10);
<?php endif; ?>
</script>
</body>
</html>
