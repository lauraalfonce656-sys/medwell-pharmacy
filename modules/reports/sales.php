<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Report.class.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$currentPage = 'reports';
$report = new Report();

// Default date range: last 30 days
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';

$salesData = $report->getSalesReport($startDate, $endDate, $groupBy);
$summary = $report->getSalesSummary($startDate, $endDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - MedWell Pharmacy</title>
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
        .filter-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e8ecf1; margin-bottom: 24px; }
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
        .badge-revenue { background: #E8F5E9; color: #2E7D32; padding: 5px 12px; border-radius: 8px; font-weight: 600; }
        .form-control:focus, .form-select:focus { border-color: var(--mw-green); box-shadow: 0 0 0 0.2rem rgba(124,179,66,0.25); }
        @media (max-width: 991px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
        @media print {
            .sidebar, .top-bar, .filter-card, .no-print { display: none !important; }
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
            <h4><i class="ri-line-chart-line" style="color:var(--mw-green)"></i> Sales Report</h4>
            <div class="d-flex gap-2 no-print">
                <a href="index.php" class="btn btn-sm btn-outline-mw"><i class="ri-arrow-left-line"></i> Back</a>
            </div>
        </div>

        <div class="content-area">
            <!-- Filters -->
            <div class="filter-card no-print">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Group By</label>
                        <select name="group_by" class="form-select">
                            <option value="daily" <?php echo $groupBy==='daily'?'selected':''; ?>>Daily</option>
                            <option value="weekly" <?php echo $groupBy==='weekly'?'selected':''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $groupBy==='monthly'?'selected':''; ?>>Monthly</option>
                            <option value="yearly" <?php echo $groupBy==='yearly'?'selected':''; ?>>Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-mw"><i class="ri-filter-3-line"></i> Apply</button>
                        <button type="button" class="btn btn-outline-mw" onclick="exportCSV()"><i class="ri-file-excel-2-line"></i> Excel</button>
                        <button type="button" class="btn btn-outline-mw" onclick="exportPDF()"><i class="ri-file-pdf-2-line"></i> PDF</button>
                        <button type="button" class="btn btn-outline-mw" onclick="window.print()"><i class="ri-printer-line"></i> Print</button>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:var(--mw-green-bg);color:var(--mw-green);"><i class="ri-money-dollar-circle-line"></i></div>
                            <div><p>Total Revenue</p><h3>$<?php echo number_format($summary['total_revenue'], 2); ?></h3></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#E3F2FD;color:#2196F3;"><i class="ri-calculator-line"></i></div>
                            <div><p>Average Sale</p><h3>$<?php echo number_format($summary['avg_sale'], 2); ?></h3></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#FFF3E0;color:#FF9800;"><i class="ri-arrow-up-circle-line"></i></div>
                            <div><p>Highest Sale</p><h3>$<?php echo number_format($summary['highest_sale'], 2); ?></h3></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" style="background:#F3E5F5;color:#9C27B0;"><i class="ri-exchange-line"></i></div>
                            <div><p>Total Transactions</p><h3><?php echo number_format($summary['total_transactions']); ?></h3></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="chart-card">
                <h5><i class="ri-line-chart-line" style="color:var(--mw-green)"></i> Sales Trend</h5>
                <canvas id="salesChart" height="100"></canvas>
            </div>

            <!-- Data Table -->
            <div class="data-card">
                <h5><i class="ri-table-line" style="color:var(--mw-green)"></i> Sales Data</h5>
                <div class="table-responsive">
                    <table id="salesTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Number of Sales</th>
                                <th>Total Revenue</th>
                                <th>Average Sale</th>
                                <th>Tax Collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($salesData)): ?>
                                <?php foreach($salesData as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['period']); ?></td>
                                    <td><?php echo number_format($row['num_sales']); ?></td>
                                    <td><span class="badge-revenue">$<?php echo number_format($row['total_revenue'], 2); ?></span></td>
                                    <td>$<?php echo number_format($row['avg_sale'], 2); ?></td>
                                    <td>$<?php echo number_format($row['tax_collected'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No sales data found for the selected period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
        // DataTable
        $(document).ready(function() {
            $('#salesTable').DataTable({
                pageLength: 10,
                order: [[0, 'desc']],
                language: { search: '', searchPlaceholder: 'Search...' }
            });
        });

        // Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode($salesData ?: []); ?>;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.period),
                datasets: [{
                    label: 'Revenue ($)',
                    data: salesData.map(d => parseFloat(d.total_revenue)),
                    borderColor: '#7CB342',
                    backgroundColor: 'rgba(124,179,66,0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#7CB342',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: ctx => '$' + ctx.parsed.y.toLocaleString('en-US', {minimumFractionDigits:2})
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '$' + v.toLocaleString() }
                    }
                }
            }
        });

        // Export CSV
        function exportCSV() {
            const table = document.getElementById('salesTable');
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
            a.href = url; a.download = 'sales_report_<?php echo $startDate; ?>_to_<?php echo $endDate; ?>.csv';
            a.click(); URL.revokeObjectURL(url);
        }

        // Export PDF (opens print dialog)
        function exportPDF() { window.print(); }
    </script>
</body>
</html>
