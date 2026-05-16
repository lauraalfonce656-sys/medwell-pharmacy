<?php
/**
 * MedWell Pharmacy - Medicine Class
 * 
 * CRUD operations and queries for the medicines table.
 * All queries use prepared statements via PDO.
 * PHP 8.0+
 */

declare(strict_types=1);

class Medicine
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all medicines with optional filters, search, pagination.
     *
     * @param  array  $filters Associative array of column => value filters.
     * @param  string $search  Search term (matches name, generic_name, batch_number).
     * @param  int    $limit   Rows per page.
     * @param  int    $offset  Offset for pagination.
     * @return array  Array of medicine records.
     */
    public function getAll(array $filters = [], string $search = '', int $limit = 50, int $offset = 0): array
    {
        $sql    = 'SELECT m.*, mc.name AS category_name, s.name AS supplier_name 
                   FROM medicines m 
                   LEFT JOIN medicine_categories mc ON m.category_id = mc.id 
                   LEFT JOIN suppliers s ON m.supplier_id = s.id 
                   WHERE 1=1';
        $params = [];

        // Apply filters
        if (!empty($filters['category_id'])) {
            $sql .= ' AND m.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }
        if (!empty($filters['supplier_id'])) {
            $sql .= ' AND m.supplier_id = :supplier_id';
            $params[':supplier_id'] = $filters['supplier_id'];
        }
        if (isset($filters['is_active'])) {
            $sql .= ' AND m.is_active = :is_active';
            $params[':is_active'] = $filters['is_active'];
        }
        if (isset($filters['stock_status'])) {
            if ($filters['stock_status'] === 'out_of_stock') {
                $sql .= ' AND m.quantity = 0';
            } elseif ($filters['stock_status'] === 'low_stock') {
                $sql .= ' AND m.quantity > 0 AND m.quantity <= m.min_stock_level';
            } elseif ($filters['stock_status'] === 'in_stock') {
                $sql .= ' AND m.quantity > m.min_stock_level';
            }
        }
        if (isset($filters['expiry_filter'])) {
            if ($filters['expiry_filter'] === 'expired') {
                $sql .= ' AND m.expiry_date IS NOT NULL AND m.expiry_date < CURDATE()';
            } elseif ($filters['expiry_filter'] === 'expiring_30') {
                $sql .= ' AND m.expiry_date IS NOT NULL AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
            } elseif ($filters['expiry_filter'] === 'expiring_90') {
                $sql .= ' AND m.expiry_date IS NOT NULL AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)';
            } elseif ($filters['expiry_filter'] === 'safe') {
                $sql .= ' AND m.expiry_date IS NOT NULL AND m.expiry_date > DATE_ADD(CURDATE(), INTERVAL 90 DAY)';
            }
        }

        // Search
        if ($search !== '') {
            $sql .= ' AND (m.name LIKE :search OR m.generic_name LIKE :search2 OR m.batch_number LIKE :search3)';
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        $sql .= ' ORDER BY m.name ASC LIMIT :limit OFFSET :offset';
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);

        // Bind limit/offset as integers
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        // Bind other params
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a single medicine by ID.
     *
     * @param  int $id Medicine ID.
     * @return array|null Medicine record or null.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mc.name AS category_name, s.name AS supplier_name 
             FROM medicines m 
             LEFT JOIN medicine_categories mc ON m.category_id = mc.id 
             LEFT JOIN suppliers s ON m.supplier_id = s.id 
             WHERE m.id = :id 
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new medicine record.
     *
     * @param  array $data Associative array of column => value.
     * @return int|false Inserted ID or false on failure.
     */
    public function create(array $data): int|false
    {
        $allowed = [
            'name', 'generic_name', 'brand', 'category_id', 'supplier_id', 'batch_number',
            'barcode', 'description', 'unit', 'price', 'cost_price',
            'quantity', 'min_stock_level', 'manufacture_date', 'expiry_date',
            'is_active',
        ];

        $fields = [];
        $placeholders = [];
        $params = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]       = $col;
                $placeholders[] = ":{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fieldsStr     = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);

        $sql = "INSERT INTO medicines ({$fieldsStr}) VALUES ({$placeholdersStr})";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($params)) {
            return (int) $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update a medicine record.
     *
     * @param  int   $id   Medicine ID.
     * @param  array $data Associative array of column => value.
     * @return bool  True on success.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'name', 'generic_name', 'brand', 'category_id', 'supplier_id', 'batch_number',
            'barcode', 'description', 'unit', 'price', 'cost_price',
            'quantity', 'min_stock_level', 'manufacture_date', 'expiry_date',
            'is_active',
        ];

        $setClauses = [];
        $params = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[]      = "{$col} = :u_{$col}";
                $params[":u_{$col}"] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setStr = implode(', ', $setClauses);
        $sql = "UPDATE medicines SET {$setStr} WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a medicine record (soft or hard based on is_active flag approach).
     *
     * @param  int  $id Medicine ID.
     * @return bool True on success.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM medicines WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Soft-delete a medicine by setting is_active = 0.
     *
     * @param  int  $id Medicine ID.
     * @return bool True on success.
     */
    public function softDelete(int $id): bool
    {
        return $this->update($id, ['is_active' => 0]);
    }

    /**
     * Get medicines by category.
     *
     * @param  int $categoryId Category ID.
     * @return array Array of medicine records.
     */
    public function getByCategory(int $categoryId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mc.name AS category_name 
             FROM medicines m 
             LEFT JOIN medicine_categories mc ON m.category_id = mc.id 
             WHERE m.category_id = :category_id AND m.is_active = 1 
             ORDER BY m.name ASC'
        );
        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Search medicines by name, generic name, or batch number.
     *
     * @param  string $query Search query.
     * @return array  Matching medicine records.
     */
    public function search(string $query): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mc.name AS category_name 
             FROM medicines m 
             LEFT JOIN medicine_categories mc ON m.category_id = mc.id 
             WHERE m.is_active = 1 
               AND (m.name LIKE :q OR m.generic_name LIKE :q2 OR m.batch_number LIKE :q3) 
             ORDER BY m.name ASC 
             LIMIT 50'
        );
        $stmt->execute([
            ':q'  => "%{$query}%",
            ':q2' => "%{$query}%",
            ':q3' => "%{$query}%",
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Get medicines expiring within a given number of days.
     *
     * @param  int $days Number of days threshold.
     * @return array Array of expiring medicines.
     */
    public function getExpiringSoon(int $days = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mc.name AS category_name 
             FROM medicines m 
             LEFT JOIN medicine_categories mc ON m.category_id = mc.id 
             WHERE m.is_active = 1 
               AND m.expiry_date IS NOT NULL 
               AND m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY) 
             ORDER BY m.expiry_date ASC'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get medicines that are at or below their minimum stock level.
     *
     * @return array Array of low-stock medicines.
     */
    public function getLowStock(): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, mc.name AS category_name 
             FROM medicines m 
             LEFT JOIN medicine_categories mc ON m.category_id = mc.id 
             WHERE m.is_active = 1 
               AND m.quantity <= m.min_stock_level 
             ORDER BY (m.quantity / NULLIF(m.min_stock_level, 0)) ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Update stock quantity for a medicine.
     *
     * @param  int    $id       Medicine ID.
     * @param  int    $quantity Quantity change (positive to add, negative to subtract).
     * @param  string $type     Movement type: 'purchase', 'sale', 'adjustment', 'return', 'expired'.
     * @return bool   True on success.
     */
    public function updateStock(int $id, int $quantity, string $type = 'adjustment'): bool
    {
        // Get current quantity
        $current = $this->getById($id);
        if ($current === null) {
            return false;
        }

        $qtyBefore = (int) $current['quantity'];
        $qtyAfter  = $qtyBefore + $quantity;

        if ($qtyAfter < 0) {
            $qtyAfter = 0;
        }

        // Update quantity
        $stmt = $this->db->prepare('UPDATE medicines SET quantity = :qty WHERE id = :id');
        $result = $stmt->execute([':qty' => $qtyAfter, ':id' => $id]);

        if ($result) {
            logInventory($id, $type, $qtyBefore, $qtyAfter, $type, null, "Stock updated by {$quantity}");
        }

        return $result;
    }

    /**
     * Get total count of active medicines.
     *
     * @return int Total count.
     */
    public function getTotalCount(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM medicines WHERE is_active = 1');
        $stmt->execute();
        $row = $stmt->fetch();
        return $row !== false ? (int) $row['cnt'] : 0;
    }

    /**
     * Get all medicine categories.
     *
     * @return array Array of category records.
     */
    public function getCategories(): array
    {
        $stmt = $this->db->prepare(
            'SELECT mc.*, 
                    (SELECT COUNT(*) FROM medicines WHERE category_id = mc.id AND is_active = 1) AS medicine_count 
             FROM medicine_categories mc 
             ORDER BY mc.name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a single category by ID.
     *
     * @param  int $id Category ID.
     * @return array|null Category record or null.
     */
    public function getCategoryById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM medicine_categories WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new medicine category.
     *
     * @param  array $data Associative array with 'name' and optional 'description'.
     * @return int|false Inserted ID or false on failure.
     */
    public function createCategory(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medicine_categories (name, description) VALUES (:name, :description)'
        );
        if ($stmt->execute([':name' => $data['name'], ':description' => $data['description'] ?? null])) {
            return (int) $this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Update a medicine category.
     *
     * @param  int   $id   Category ID.
     * @param  array $data Associative array with 'name' and/or 'description'.
     * @return bool  True on success.
     */
    public function updateCategory(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE medicine_categories SET name = :name, description = :description WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Delete a medicine category.
     *
     * @param  int $id Category ID.
     * @return bool True on success.
     */
    public function deleteCategory(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM medicine_categories WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Count medicines linked to a category.
     *
     * @param  int $categoryId Category ID.
     * @return int Medicine count.
     */
    public function countMedicinesByCategory(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM medicines WHERE category_id = :category_id'
        );
        $stmt->execute([':category_id' => $categoryId]);
        $row = $stmt->fetch();
        return $row !== false ? (int) $row['cnt'] : 0;
    }

    /**
     * Get inventory logs for a medicine.
     *
     * @param  int $medicineId Medicine ID.
     * @param  int $limit      Max rows to return.
     * @return array Array of inventory log records.
     */
    public function getInventoryLogs(int $medicineId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT il.*, u.full_name AS user_name 
             FROM inventory_logs il 
             LEFT JOIN users u ON il.user_id = u.id 
             WHERE il.medicine_id = :medicine_id 
             ORDER BY il.created_at DESC 
             LIMIT :limit'
        );
        $stmt->bindValue(':medicine_id', $medicineId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get recent sale items for a medicine.
     *
     * @param  int $medicineId Medicine ID.
     * @param  int $limit      Max rows to return.
     * @return array Array of sale item records.
     */
    public function getRecentSales(int $medicineId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT si.*, s.invoice_number, s.created_at AS sale_date, s.payment_method, s.payment_status,
                    c.name AS customer_name
             FROM sale_items si 
             INNER JOIN sales s ON si.sale_id = s.id
             LEFT JOIN customers c ON s.customer_id = c.id
             WHERE si.medicine_id = :medicine_id 
             ORDER BY s.created_at DESC 
             LIMIT :limit'
        );
        $stmt->bindValue(':medicine_id', $medicineId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get statistics grouped by category.
     *
     * @return array Category-wise stats (count, total stock value).
     */
    public function getCategoryStats(): array
    {
        $stmt = $this->db->prepare(
            'SELECT mc.id, mc.name, COUNT(m.id) AS medicine_count, 
                    SUM(m.quantity) AS total_stock, 
                    SUM(m.quantity * m.cost_price) AS stock_value 
             FROM medicine_categories mc 
             LEFT JOIN medicines m ON mc.id = m.category_id AND m.is_active = 1 
             GROUP BY mc.id, mc.name 
             ORDER BY mc.name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
