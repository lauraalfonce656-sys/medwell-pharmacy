<?php
/**
 * MedWell Pharmacy - AJAX: Get Dashboard Stats
 *
 * Returns dashboard statistics for AJAX-powered real-time refresh.
 * Supports period-based filtering: today, week, month, year.
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// ─── Authentication Check ─────────────────────────────────────────────────
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ─── Validate Request Method ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // ─── Input Parameters ──────────────────────────────────────────────
    $period = $_GET['period'] ?? 'today';
    $validPeriods = ['today', 'week', 'month', 'year'];

    if (!in_array($period, $validPeriods, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid period. Use: today, week, month, year.']);
        exit;
    }

    // ─── Determine Date Range ──────────────────────────────────────────
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));

    $startDate = match ($period) {
        'today' => $now->format('Y-m-d'),
        'week'  => $now->modify('monday this week')->format('Y-m-d'),
        'month' => $now->format('Y-m-01'),
        'year'  => $now->format('Y-01-01'),
    };

    $endDate = $now->format('Y-m-d');

    // Reset $now since modify() above mutated it
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));

    $db = Database::getInstance()->getConnection();

    // ─── Total Medicines ───────────────────────────────────────────────
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM medicines WHERE is_active = 1');
    $stmt->execute();
    $totalMedicines = (int) ($stmt->fetch()['cnt'] ?? 0);

    // ─── Total Sales (in period) ───────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS cnt
         FROM sales
         WHERE DATE(created_at) BETWEEN :start AND :end
           AND payment_status != :status'
    );
    $stmt->execute([':start' => $startDate, ':end' => $endDate, ':status' => 'refunded']);
    $totalSales = (int) ($stmt->fetch()['cnt'] ?? 0);

    // ─── Total Revenue (in period) ─────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(total_amount), 0) AS total
         FROM sales
         WHERE DATE(created_at) BETWEEN :start AND :end
           AND payment_status != :status'
    );
    $stmt->execute([':start' => $startDate, ':end' => $endDate, ':status' => 'refunded']);
    $totalRevenue = (float) ($stmt->fetch()['total'] ?? 0);

    // ─── Total Customers ───────────────────────────────────────────────
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM customers WHERE is_active = 1');
    $stmt->execute();
    $totalCustomers = (int) ($stmt->fetch()['cnt'] ?? 0);

    // ─── Low Stock Count ───────────────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS cnt
         FROM medicines
         WHERE is_active = 1 AND quantity <= min_stock_level'
    );
    $stmt->execute();
    $lowStockCount = (int) ($stmt->fetch()['cnt'] ?? 0);

    // ─── Expiring Count (within 90 days) ───────────────────────────────
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS cnt
         FROM medicines
         WHERE is_active = 1
           AND expiry_date IS NOT NULL
           AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)"
    );
    $stmt->execute();
    $expiringCount = (int) ($stmt->fetch()['cnt'] ?? 0);

    // ─── Sales Chart Data (daily breakdown in period) ──────────────────
    $chartFormat = match ($period) {
        'today' => '%Y-%m-%d %H:00',   // hourly for today
        'week'  => '%Y-%m-%d',          // daily for week
        'month' => '%Y-%m-%d',          // daily for month
        'year'  => '%Y-%m',             // monthly for year
    };

    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, :fmt) AS period_label,
                COUNT(*) AS transaction_count,
                COALESCE(SUM(total_amount), 0) AS revenue
         FROM sales
         WHERE DATE(created_at) BETWEEN :start AND :end
           AND payment_status != 'refunded'
         GROUP BY period_label
         ORDER BY period_label ASC"
    );
    $stmt->bindValue(':fmt',  $chartFormat);
    $stmt->bindValue(':start', $startDate);
    $stmt->bindValue(':end',   $endDate);
    $stmt->execute();
    $salesChartData = $stmt->fetchAll();

    // ─── Revenue Chart Data ────────────────────────────────────────────
    $revenueFormat = match ($period) {
        'today' => '%Y-%m-%d %H:00',
        'week'  => '%Y-%m-%d',
        'month' => '%Y-%m-%d',
        'year'  => '%Y-%m',
    };

    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, :fmt) AS period_label,
                COALESCE(SUM(subtotal), 0)  AS subtotal_sum,
                COALESCE(SUM(tax_amount), 0) AS tax_sum,
                COALESCE(SUM(discount_amount), 0) AS discount_sum,
                COALESCE(SUM(total_amount), 0) AS total_sum
         FROM sales
         WHERE DATE(created_at) BETWEEN :start AND :end
           AND payment_status != 'refunded'
         GROUP BY period_label
         ORDER BY period_label ASC"
    );
    $stmt->bindValue(':fmt',  $revenueFormat);
    $stmt->bindValue(':start', $startDate);
    $stmt->bindValue(':end',   $endDate);
    $stmt->execute();
    $revenueChartData = $stmt->fetchAll();

    // ─── Build Response ────────────────────────────────────────────────
    echo json_encode([
        'success'            => true,
        'period'             => $period,
        'start_date'         => $startDate,
        'end_date'           => $endDate,
        'total_medicines'    => $totalMedicines,
        'total_sales'        => $totalSales,
        'total_revenue'      => $totalRevenue,
        'total_customers'    => $totalCustomers,
        'low_stock_count'    => $lowStockCount,
        'expiring_count'     => $expiringCount,
        'sales_chart_data'   => $salesChartData,
        'revenue_chart_data' => $revenueChartData,
    ]);

} catch (PDOException $e) {
    error_log('get_dashboard_stats.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching dashboard statistics.',
    ]);
} catch (Throwable $e) {
    error_log('get_dashboard_stats.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
