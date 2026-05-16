<?php
/**
 * MedWell Pharmacy - Report Class
 * 
 * Reporting and analytics: sales, profit, inventory, expiry,
 * stock movement, customer, supplier, and CSV export.
 * PHP 8.0+
 */

declare(strict_types=1);

class Report
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get sales report grouped by a time period.
     *
     * @param  string $startDate Y-m-d.
     * @param  string $endDate   Y-m-d.
     * @param  string $groupBy   'daily', 'weekly', 'monthly', 'yearly'.
     * @return array  Grouped sales data.
     */
    public function getSalesReport(string $startDate, string $endDate, string $groupBy = 'daily'): array
    {
        $dateFormat = match ($groupBy) {
            'daily'   => '%Y-%m-%d',
            'weekly'  => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly'  => '%Y',
            default   => '%Y-%m-%d',
        };

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(s.created_at, :fmt) AS period, 
                    COUNT(*) AS num_sales, 
                    COALESCE(SUM(s.subtotal), 0) AS subtotal, 
                    COALESCE(SUM(s.discount), 0) AS discount, 
                    COALESCE(SUM(s.tax_amount), 0) AS tax, 
                    COALESCE(SUM(s.total_amount), 0) AS total, 
                    COALESCE(AVG(s.total_amount), 0) AS avg_sale 
             FROM sales s 
             WHERE DATE(s.created_at) BETWEEN :start AND :end 
               AND s.payment_status != 'refunded' 
             GROUP BY period 
             ORDER BY period ASC"
        );
        $stmt->execute([
            ':fmt'   => $dateFormat,
            ':start' => $startDate,
            ':end'   => $endDate,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Get profit report between two dates.
     *
     * @param  string $startDate Y-m-d.
     * @param  string $endDate   Y-m-d.
     * @return array Profit data with daily breakdown.
     */
    public function getProfitReport(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(s.created_at) AS sale_date, 
                    COALESCE(SUM(si.total_price), 0) AS revenue, 
                    COALESCE(SUM(si.quantity * si.cost_price), 0) AS cost, 
                    COALESCE(SUM(si.total_price - (si.quantity * si.cost_price)), 0) AS profit 
             FROM sale_items si 
             INNER JOIN sales s ON si.sale_id = s.id 
             WHERE DATE(s.created_at) BETWEEN :start AND :end 
               AND s.payment_status != 'refunded' 
             GROUP BY sale_date 
             ORDER BY sale_date ASC"
        );
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Get inventory report — current stock levels and values.
     *
     * @return array Inventory data.
     */
    public function getInventoryReport(): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.name, m.generic_name, m.batch_number, m.quantity, 
                    m.min_stock_level, m.selling_price, m.cost_price, 
                    m.expiry_date, c.name AS category_name, s.name AS supplier_name, 
                    (m.quantity * m.cost_price) AS stock_value 
             FROM medicines m 
             LEFT JOIN categories c ON m.category_id = c.id 
             LEFT JOIN suppliers s ON m.supplier_id = s.id 
             WHERE m.is_active = 1 
             ORDER BY m.name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get expiry report for medicines expiring within a given number of days.
     *
     * @param  int $days Number of days threshold.
     * @return array Expiring medicines.
     */
    public function getExpiryReport(int $days = 90): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.name, m.generic_name, m.batch_number, m.quantity, 
                    m.cost_price, m.expiry_date, c.name AS category_name, 
                    DATEDIFF(m.expiry_date, CURDATE()) AS days_until_expiry 
             FROM medicines m 
             LEFT JOIN categories c ON m.category_id = c.id 
             WHERE m.is_active = 1 
               AND m.expiry_date IS NOT NULL 
               AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY) 
             ORDER BY m.expiry_date ASC'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get stock movement report between two dates.
     *
     * @param  string $startDate Y-m-d.
     * @param  string $endDate   Y-m-d.
     * @return array Stock movement logs.
     */
    public function getStockMovementReport(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT il.id, il.medicine_id, m.name AS medicine_name, m.batch_number, 
                    il.movement_type, il.quantity_before, il.quantity_after, 
                    (il.quantity_after - il.quantity_before) AS change_amount, 
                    il.reference_type, il.reference_id, il.notes, 
                    il.created_at, u.full_name AS created_by_name 
             FROM inventory_logs il 
             LEFT JOIN medicines m ON il.medicine_id = m.id 
             LEFT JOIN users u ON il.created_by = u.id 
             WHERE DATE(il.created_at) BETWEEN :start AND :end 
             ORDER BY il.created_at DESC'
        );
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        return $stmt->fetchAll();
    }

    /**
     * Get customer report — purchase totals and activity.
     *
     * @return array Customer summary data.
     */
    public function getCustomerReport(): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.full_name, c.email, c.phone, c.loyalty_points, 
                    c.created_at, 
                    COUNT(s.id) AS total_purchases, 
                    COALESCE(SUM(s.total_amount), 0) AS total_spent, 
                    MAX(s.created_at) AS last_purchase_date 
             FROM customers c 
             LEFT JOIN sales s ON c.id = s.customer_id AND s.payment_status != "refunded" 
             GROUP BY c.id 
             ORDER BY total_spent DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get supplier report — order counts and totals.
     *
     * @return array Supplier summary data.
     */
    public function getSupplierReport(): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.name, s.contact_person, s.email, s.phone, 
                    COUNT(m.id) AS medicine_count, 
                    COALESCE(SUM(m.quantity), 0) AS total_stock, 
                    COALESCE(SUM(m.quantity * m.cost_price), 0) AS stock_value 
             FROM suppliers s 
             LEFT JOIN medicines m ON s.id = m.supplier_id AND m.is_active = 1 
             GROUP BY s.id 
             ORDER BY s.name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Export data to a CSV file for download.
     *
     * @param  array  $data     Array of associative rows.
     * @param  string $filename Filename for download (without extension).
     * @return void   Outputs CSV with headers for download.
     */
    public function exportToCSV(array $data, string $filename = 'report'): void
    {
        if (empty($data)) {
            return;
        }

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row
        $headers = array_keys($data[0]);
        fputcsv($output, $headers);

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
