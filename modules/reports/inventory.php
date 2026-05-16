<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Report.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$currentPage = 'reports';
$report = new Report();

$inventoryData = $report->getInventoryReport();
$summary = $report->getInventorySummary();
$stockDistribution = $report->getStockDistribution();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - MedWell Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root { --mw-green: #7CB342; --mw-green-dark: #689F38; --mw-green-light: #9CCC65; --mw-green-bg: #f1f8e9; }
        body { background: #f5f7fa; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #558B2F 0%, #33691E 100%); min-height: 100vh; position: fixed; left: 0; top: 0; z-index: 1000; }
        .sidebar-brand { padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; gap: 12px; }
        .sidebar-brand .brand-icon { width: 42px; height: 42px; background: var(--mw-green-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #33691E; }
        .sidebar-brand h5 { color: #fff; margin: 0; font-weight: 700; font-size: 1.15rem; }
        .sidebar-brand small { color: rgba(255,255,255,0.65); font-size: 0.75rem; }
        .sidebar-nav { padding: 16px 12px; }
        .sidebar-nav .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 16px; border-radius: 10px; color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem; margin-bottom: 4px; transition: all 0.2s; }
        .sidebar-nav .nav-item:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .sidebar-nav .nav-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; }
        .sidebar-nav .nav-item i { font-size: 1.2rem; }
        .main-content { margin-left: 260px; }
        .top-bar { background: #fff; padding: 16px 30px; border-bottom: 1px solid #e8ecf1; display: flex; justify-content: space-between; align-items: center; }
        .top-bar h4 { margin: 0; font-weight: 700; color: #2d3748; }
        .content-area { padding: 30px; }
        .stat-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e8ecf1; }
        .stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-card h3 { font-weight: 800; margin: 0; font-size: 1.5rem; color: #2d3748; }
        .stat-card p { margin: 0; color: #718096; font-size: 0.85rem; }
        .chart-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e8ecf1; margin-bottom: 24px; }
        .chart-card h5 { font-weight: 700; color: #2d3748; margin-bottom: 20px; }
        .btn-mw { background: var(--mw-green); color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 600; font-size: 0.88rem; transition: all 0.2s; }
        .btn-mw:hover { background: var(--mw-green-dark); color: #fff; }
        .btn-outline-mw { background: #fff; color: var(--mw-green); border: 2px solid var(--mw-green); border-radius: 10px; padding: 8px 16px; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; }
        .btn-outline-mw:hover { background: var(--mw-green); color: #fff; }
        .data-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e8ecf1; }
        .data-card h5 { font-weight: 700; color: #2d3748; margin-bottom: 16px; }
        table.dataTable thead th { background: var(--mw-green-bg); color: #2d3748; font-weight: 600; border-bottom: 2px solid var(--mw-green-light); font-size: 0.88rem; }
        table.dataTable tbody td { font-size: 0.88rem; vertical-align: middle; }
        .badge-stock-ok { background: #E8F5E9; color: #2E7D32; padding: 5px 12px; border-radius: 8px; font-weight: 600; }
        .badge-stock-low { background: #FFF3E0; color: #E65100; padding: 5px 12px; border-radius: 8px; font-weight: 600; }
        .badge-stock-out { background: #FFEBEE; color: #C62828; padding: 5px 12px; border-radius: 8px; font-weight: 600; }
        .form-control:focus, .form-select:focus { border-color: var(--mw-green); box-shadow: 0 0 0 0.2rem rgba(124,179,66,0.25); }
        @media (max-width: 991px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
        @media print {
            .sidebar, .top-bar, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .stat-card, .chart-card, .data-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="ri-capsule-fill"></i></div>
            <div><h5>MedWell</h5><small>Pharmacy System</small></div>
        </div>
        <nav class="sidebar-nav">
            <a href="../../index.php" class="nav-item"><i class="ri-dashboard-3-line"></i> Dashboard</a>
            <a href="../medicines/index.php" class="nav-item"><i class="ri-capsule-line"></i> Medicines</a>
            <a href="../sales/index.php" class="nav-item"><i class="ri-shopping-cart-line"></i> Sales</a>
            <a href="../purchases/index.php" class="nav-item"><i class="ri-truck-line"></i> Purchases</a>
            <a href="../customers/index.php" class="nav-item"><i class="ri-user-line"></i> Customers</a>
            <a href="../suppliers/index.php" class="nav-item"><i class="ri-store-2-line"></i> Suppliers</a>
            <a href="index.php" class="nav-item active"><i class="ri-bar-chart-box-line"></i> Reports</a>
            <a href="../settings/index.php" class="nav-item"><i class="ri-settings-3-line"></i> Settings</a>
        </nav>
    </aside>

    <div class="main-content">
        <div class="top-bar">
            <h4><i class="ri-archive-line" style="color:#FF9800"></i> Inventory Report</h4>
            <div class="d-flex gap-2 no-print">
                <a href="index.php" class="btn btn-sm btn-outline-mw"><i class="ri-arrow-left-line"></i> Back</a>
                <button class="btn btn-sm btn-outline-mw" onclick="exportCSV()"><i class="ri-file-excel-2-line"></i> Export CSV</button>
                <button class="btn btn-sm btn-outline-mw" onclick="window.print()"><i class="ri-printer-line"></i> Print</button>
            </div>
        </div>

        <div class="content-area">
            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:var(--mw-green-bg);color:var(--mw-green);"><i class="ri-capsule-line"></i></div>
                            <div><p>Total Items</p><h3><?php echo number_format($summary['total_items']); ?></h3></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#E3F2FD;color:#2196F3;"><i class="ri-money-dollar-circle-line"></i></div>
                            <div><p>Total Stock Value</p><h3>$<?php echo number_format($summary['total_value'], 2); ?></h3></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#FFF3E0;color:#FF9800;"><i class="ri-error-warning-line"></i></div>
                            <div><p>Low Stock Count</p><h3><?php echo number_format($summary['low_stock']); ?></h3></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#FFEBEE;color:#F44336;"><i class="ri-close-circle-line"></i></div>
                            <div><p>Out of Stock</p><h3><?php echo number_format($summary['out_of_stock']); ?></h3></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Pie Chart -->
                <div class="col-lg-5">
                    <div class="chart-card h-100">
                        <h5><i class="ri-pie-chart-line" style="color:#FF9800"></i> Stock Status Distribution</h5>
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
                <!-- Data Table -->
                <div class="col-lg-7">
                    <div class="data-card h-100">
                        <h5><i class="ri-table-line" style="color:var(--mw-green)"></i> Inventory Details</h5>
                        <div class="table-responsive">
                            <table id="inventoryTable" class="table table-hover" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Category</th>
                                        <th>Stock Qty</th>
                                        <th>Min Level</th>
                                        <th>Value</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($inventoryData)): ?>
                                        <?php foreach($inventoryData as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($row['medicine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                                            <td><?php echo number_format($row['stock_qty']); ?></td>
                                            <td><?php echo number_format($row['min_level']); ?></td>
                                            <td>$<?php echo number_format($row['value'], 2); ?></td>
                                            <td>
                                                <?php
                                                if($row['stock_qty'] <= 0) {
                                                    echo '<span class="badge-stock-out">Out of Stock</span>';
                                                } elseif($row['stock_qty'] <= $row['min_level']) {
                                                    echo '<span class="badge-stock-low">Low Stock</span>';
                                                } else {
                                                    echo '<span class="badge-stock-ok">In Stock</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No inventory data found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#inventoryTable').DataTable({
                pageLength: 10,
                order: [[5, 'asc']],
                language: { search: '', searchPlaceholder: 'Search...' }
            });
        });

        const dist = <?php echo json_encode($stockDistribution ?: ['in_stock'=>0,'low_stock'=>0,'out_of_stock'=>0]); ?>;
        new Chart(document.getElementById('stockChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [dist.in_stock, dist.low_stock, dist.out_of_stock],
                    backgroundColor: ['#7CB342', '#FF9800', '#F44336'],
                    borderColor: ['#689F38', '#F57C00', '#D32F2F'],
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        function exportCSV() {
            const table = document.getElementById('inventoryTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach(col => rowData.push('"' + col.innerText.replace(/"/g, '""') + '"'));
                csv.push(rowData.join(','));
            });
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'inventory_report_<?php echo date('Y-m-d'); ?>.csv';
            a.click(); URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
