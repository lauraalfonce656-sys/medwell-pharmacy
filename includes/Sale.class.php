<?php
/**
 * MedWell Pharmacy - Sale Class
 * 
 * Sales management with transactional integrity, including
 * sale creation, items, inventory logs, refunds, and analytics.
 * PHP 8.0+
 */

declare(strict_types=1);

class Sale
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new sale with items and inventory updates in a transaction.
     *
     * @param  array $data  Sale header data (customer_id, payment_method, discount, notes, etc.).
     * @param  array $items Array of items: [['medicine_id' => int, 'quantity' => int, 'unit_price' => float], ...].
     * @return int|false The sale ID on success, false on failure.
     */
    public function create(array $data, array $items): int|false
    {
        if (empty($items)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Calculate totals
            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += (float) $item['unit_price'] * (int) $item['quantity'];
            }

            $discount    = (float) ($data['discount'] ?? 0);
            $taxable     = $subtotal - $discount;
            $taxAmount   = round($taxable * (TAX_RATE / 100), 2);
            $totalAmount = $taxable + $taxAmount;

            $invoiceNumber = $data['invoice_number'] ?? generateInvoiceNumber();

            // Insert sale header
            $stmt = $this->db->prepare(
                'INSERT INTO sales 
                 (invoice_number, customer_id, user_id, subtotal, discount, tax_amount, total_amount, 
                  payment_method, payment_status, notes, created_at) 
                 VALUES 
                 (:invoice_number, :customer_id, :user_id, :subtotal, :discount, :tax_amount, :total_amount, 
                  :payment_method, :payment_status, :notes, NOW())'
            );
            $stmt->execute([
                ':invoice_number' => $invoiceNumber,
                ':customer_id'    => $data['customer_id'] ?? null,
                ':user_id'        => $data['user_id'] ?? getCurrentUserId(),
                ':subtotal'       => $subtotal,
                ':discount'       => $discount,
                ':tax_amount'     => $taxAmount,
                ':total_amount'   => $totalAmount,
                ':payment_method' => $data['payment_method'] ?? 'cash',
                ':payment_status' => $data['payment_status'] ?? 'paid',
                ':notes'          => $data['notes'] ?? '',
            ]);

            $saleId = (int) $this->db->lastInsertId();

            // Insert sale items and update inventory
            $itemStmt = $this->db->prepare(
                'INSERT INTO sale_items 
                 (sale_id, medicine_id, quantity, unit_price, cost_price, total_price) 
                 VALUES 
                 (:sale_id, :medicine_id, :quantity, :unit_price, :cost_price, :total_price)'
            );

            foreach ($items as $item) {
                $medicineId = (int) $item['medicine_id'];
                $qty        = (int) $item['quantity'];
                $unitPrice  = (float) $item['unit_price'];

                // Get current medicine data for cost price and stock
                $medStmt = $this->db->prepare(
                    'SELECT quantity, cost_price FROM medicines WHERE id = :id FOR UPDATE'
                );
                $medStmt->execute([':id' => $medicineId]);
                $med = $medStmt->fetch();

                if ($med === false) {
                    throw new RuntimeException("Medicine ID {$medicineId} not found.");
                }

                if ((int) $med['quantity'] < $qty) {
                    throw new RuntimeException("Insufficient stock for medicine ID {$medicineId}.");
                }

                $costPrice  = (float) ($item['cost_price'] ?? $med['cost_price']);
                $totalPrice = $unitPrice * $qty;

                $itemStmt->execute([
                    ':sale_id'     => $saleId,
                    ':medicine_id' => $medicineId,
                    ':quantity'    => $qty,
                    ':unit_price'  => $unitPrice,
                    ':cost_price'  => $costPrice,
                    ':total_price' => $totalPrice,
                ]);

                // Deduct stock
                $qtyBefore = (int) $med['quantity'];
                $qtyAfter  = $qtyBefore - $qty;

                $updateStmt = $this->db->prepare(
                    'UPDATE medicines SET quantity = :qty WHERE id = :id'
                );
                $updateStmt->execute([':qty' => $qtyAfter, ':id' => $medicineId]);

                // Log inventory movement
                logInventory(
                    $medicineId,
                    'sale',
                    $qtyBefore,
                    $qtyAfter,
                    'sale',
                    $saleId,
                    "Sold {$qty} units - Invoice {$invoiceNumber}"
                );
            }

            // Add loyalty points if customer is set
            if (!empty($data['customer_id'])) {
                $loyaltyPoints = (int) floor($totalAmount / 1000); // 1 point per 1000 TZS
                if ($loyaltyPoints > 0) {
                    $custStmt = $this->db->prepare(
                        'UPDATE customers SET loyalty_points = loyalty_points + :points WHERE id = :id'
                    );
                    $custStmt->execute([
                        ':points' => $loyaltyPoints,
                        ':id'     => $data['customer_id'],
                    ]);
                }
            }

            $this->db->commit();
            return $saleId;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Sale::create error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all sales with optional filters, date range, and pagination.
     *
     * @param  array  $filters   Filters (payment_method, payment_status, customer_id).
     * @param  array  $dateRange ['start' => Y-m-d, 'end' => Y-m-d].
     * @param  int    $limit     Rows per page.
     * @param  int    $offset    Offset.
     * @return array  Sale records.
     */
    public function getAll(array $filters = [], array $dateRange = [], int $limit = 50, int $offset = 0): array
    {
        $sql    = 'SELECT s.*, c.full_name AS customer_name, u.full_name AS user_name 
                   FROM sales s 
                   LEFT JOIN customers c ON s.customer_id = c.id 
                   LEFT JOIN users u ON s.user_id = u.id 
                   WHERE 1=1';
        $params = [];

        if (!empty($filters['payment_method'])) {
            $sql .= ' AND s.payment_method = :payment_method';
            $params[':payment_method'] = $filters['payment_method'];
        }
        if (!empty($filters['payment_status'])) {
            $sql .= ' AND s.payment_status = :payment_status';
            $params[':payment_status'] = $filters['payment_status'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND s.customer_id = :customer_id';
            $params[':customer_id'] = $filters['customer_id'];
        }

        if (!empty($dateRange['start'])) {
            $sql .= ' AND DATE(s.created_at) >= :start_date';
            $params[':start_date'] = $dateRange['start'];
        }
        if (!empty($dateRange['end'])) {
            $sql .= ' AND DATE(s.created_at) <= :end_date';
            $params[':end_date'] = $dateRange['end'];
        }

        $sql .= ' ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset';
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a single sale by ID.
     *
     * @param  int $id Sale ID.
     * @return array|null Sale record or null.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, c.full_name AS customer_name, c.phone AS customer_phone, u.full_name AS user_name 
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             LEFT JOIN users u ON s.user_id = u.id 
             WHERE s.id = :id 
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get a sale by its invoice number.
     *
     * @param  string $invoiceNumber Invoice number.
     * @return array|null Sale record or null.
     */
    public function getByInvoice(string $invoiceNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, c.full_name AS customer_name, u.full_name AS user_name 
             FROM sales s 
             LEFT JOIN customers c ON s.customer_id = c.id 
             LEFT JOIN users u ON s.user_id = u.id 
             WHERE s.invoice_number = :invoice 
             LIMIT 1'
        );
        $stmt->execute([':invoice' => $invoiceNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all items for a sale.
     *
     * @param  int $saleId Sale ID.
     * @return array Sale items.
     */
    public function getItems(int $saleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT si.*, m.name AS medicine_name, m.generic_name, m.batch_number 
             FROM sale_items si 
             LEFT JOIN medicines m ON si.medicine_id = m.id 
             WHERE si.sale_id = :sale_id 
             ORDER BY si.id ASC'
        );
        $stmt->execute([':sale_id' => $saleId]);
        return $stmt->fetchAll();
    }

    /**
     * Get sales summary for a specific date.
     *
     * @param  string $date Date in Y-m-d format.
     * @return array Summary with total_sales, total_amount, total_tax.
     */
    public function getDailySales(string $date): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total_sales, 
                    COALESCE(SUM(total_amount), 0) AS total_amount, 
                    COALESCE(SUM(tax_amount), 0) AS total_tax, 
                    COALESCE(SUM(discount), 0) AS total_discount 
             FROM sales 
             WHERE DATE(created_at) = :date AND payment_status != "refunded"'
        );
        $stmt->execute([':date' => $date]);
        $row = $stmt->fetch();
        return $row ?: [
            'total_sales'     => 0,
            'total_amount'    => 0,
            'total_tax'       => 0,
            'total_discount'  => 0,
        ];
    }

    /**
     * Get monthly sales summary.
     *
     * @param  int $month Month (1-12).
     * @param  int $year  Year.
     * @return array Monthly summary.
     */
    public function getMonthlySales(int $month, int $year): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total_sales, 
                    COALESCE(SUM(total_amount), 0) AS total_amount, 
                    COALESCE(SUM(tax_amount), 0) AS total_tax, 
                    COALESCE(SUM(discount), 0) AS total_discount 
             FROM sales 
             WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year 
               AND payment_status != "refunded"'
        );
        $stmt->execute([':month' => $month, ':year' => $year]);
        $row = $stmt->fetch();
        return $row ?: [
            'total_sales'     => 0,
            'total_amount'    => 0,
            'total_tax'       => 0,
            'total_discount'  => 0,
        ];
    }

    /**
     * Get revenue summary grouped by period.
     *
     * @param  string $period Grouping: 'daily', 'weekly', 'monthly', 'yearly'.
     * @return array Revenue summary rows.
     */
    public function getRevenueSummary(string $period = 'daily'): array
    {
        $dateFormat = match ($period) {
            'daily'   => '%Y-%m-%d',
            'weekly'  => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly'  => '%Y',
            default   => '%Y-%m-%d',
        };

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(created_at, :fmt) AS period, 
                    COUNT(*) AS total_sales, 
                    COALESCE(SUM(total_amount), 0) AS revenue, 
                    COALESCE(SUM(tax_amount), 0) AS tax, 
                    COALESCE(SUM(discount), 0) AS discount 
             FROM sales 
             WHERE payment_status != 'refunded' 
             GROUP BY period 
             ORDER BY period DESC 
             LIMIT 60"
        );
        $stmt->bindValue(':fmt', $dateFormat);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get top-selling medicines.
     *
     * @param  int $limit Number of results.
     * @return array Top-selling medicines with quantities and revenue.
     */
    public function getTopSellingMedicines(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.name, m.generic_name, 
                    SUM(si.quantity) AS total_qty, 
                    SUM(si.total_price) AS total_revenue 
             FROM sale_items si 
             INNER JOIN medicines m ON si.medicine_id = m.id 
             INNER JOIN sales s ON si.sale_id = s.id AND s.payment_status != "refunded" 
             GROUP BY si.medicine_id 
             ORDER BY total_qty DESC 
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get sales breakdown by payment method.
     *
     * @return array Payment method statistics.
     */
    public function getSalesByPaymentMethod(): array
    {
        $stmt = $this->db->query(
            'SELECT payment_method, 
                    COUNT(*) AS count, 
                    COALESCE(SUM(total_amount), 0) AS total 
             FROM sales 
             WHERE payment_status != "refunded" 
             GROUP BY payment_method 
             ORDER BY total DESC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Refund a sale — restore stock and mark sale as refunded.
     *
     * @param  int $saleId Sale ID.
     * @return bool True on success.
     */
    public function refund(int $saleId): bool
    {
        try {
            $this->db->beginTransaction();

            // Get sale details
            $sale = $this->getById($saleId);
            if ($sale === null || $sale['payment_status'] === 'refunded') {
                throw new RuntimeException('Sale not found or already refunded.');
            }

            // Mark sale as refunded
            $updateStmt = $this->db->prepare(
                'UPDATE sales SET payment_status = "refunded" WHERE id = :id'
            );
            $updateStmt->execute([':id' => $saleId]);

            // Restore stock for each item
            $items = $this->getItems($saleId);
            foreach ($items as $item) {
                $medicineId = (int) $item['medicine_id'];
                $qty        = (int) $item['quantity'];

                $medStmt = $this->db->prepare(
                    'SELECT quantity FROM medicines WHERE id = :id FOR UPDATE'
                );
                $medStmt->execute([':id' => $medicineId]);
                $med = $medStmt->fetch();

                if ($med !== false) {
                    $qtyBefore = (int) $med['quantity'];
                    $qtyAfter  = $qtyBefore + $qty;

                    $restoreStmt = $this->db->prepare(
                        'UPDATE medicines SET quantity = :qty WHERE id = :id'
                    );
                    $restoreStmt->execute([':qty' => $qtyAfter, ':id' => $medicineId]);

                    logInventory(
                        $medicineId,
                        'return',
                        $qtyBefore,
                        $qtyAfter,
                        'refund',
                        $saleId,
                        "Refund - Invoice {$sale['invoice_number']}"
                    );
                }
            }

            // Deduct loyalty points if customer existed
            if (!empty($sale['customer_id'])) {
                $pointsToDeduct = (int) floor((float) $sale['total_amount'] / 1000);
                if ($pointsToDeduct > 0) {
                    $custStmt = $this->db->prepare(
                        'UPDATE customers SET loyalty_points = GREATEST(loyalty_points - :points, 0) WHERE id = :id'
                    );
                    $custStmt->execute([
                        ':points' => $pointsToDeduct,
                        ':id'     => $sale['customer_id'],
                    ]);
                }
            }

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Sale::refund error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total revenue between two dates.
     *
     * @param  string $startDate Y-m-d.
     * @param  string $endDate   Y-m-d.
     * @return float Total revenue.
     */
    public function getTotalRevenue(string $startDate, string $endDate): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) AS revenue 
             FROM sales 
             WHERE DATE(created_at) BETWEEN :start AND :end 
               AND payment_status != "refunded"'
        );
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        $row = $stmt->fetch();
        return $row !== false ? (float) $row['revenue'] : 0.0;
    }

    /**
     * Get profit analysis between two dates.
     *
     * @param  string $startDate Y-m-d.
     * @param  string $endDate   Y-m-d.
     * @return array Profit analysis data.
     */
    public function getProfitAnalysis(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(si.total_price), 0) AS total_revenue, 
                    COALESCE(SUM(si.quantity * si.cost_price), 0) AS total_cost, 
                    COALESCE(SUM(si.total_price - (si.quantity * si.cost_price)), 0) AS gross_profit 
             FROM sale_items si 
             INNER JOIN sales s ON si.sale_id = s.id 
             WHERE DATE(s.created_at) BETWEEN :start AND :end 
               AND s.payment_status != "refunded"'
        );
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        $row = $stmt->fetch();

        if ($row === false) {
            return [
                'total_revenue' => 0,
                'total_cost'    => 0,
                'gross_profit'  => 0,
                'profit_margin' => 0,
            ];
        }

        $revenue     = (float) $row['total_revenue'];
        $cost        = (float) $row['total_cost'];
        $profit      = (float) $row['gross_profit'];
        $margin      = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

        return [
            'total_revenue' => $revenue,
            'total_cost'    => $cost,
            'gross_profit'  => $profit,
            'profit_margin' => $margin,
        ];
    }
}
