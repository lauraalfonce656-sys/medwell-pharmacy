<?php
/**
 * MedWell Pharmacy - Customer Class
 * 
 * CRUD operations for customers with loyalty points management.
 * PHP 8.0+
 */

declare(strict_types=1);

class Customer
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all customers with optional search and pagination.
     *
     * @param  string $search Search term (name, email, phone).
     * @param  int    $limit  Rows per page.
     * @param  int    $offset Offset.
     * @return array  Customer records.
     */
    public function getAll(string $search = '', int $limit = 50, int $offset = 0): array
    {
        $sql    = 'SELECT * FROM customers WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (full_name LIKE :search OR email LIKE :search2 OR phone LIKE :search3)';
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        $sql .= ' ORDER BY full_name ASC LIMIT :limit OFFSET :offset';
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
     * Get a single customer by ID.
     *
     * @param  int $id Customer ID.
     * @return array|null Customer record or null.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new customer.
     *
     * @param  array $data Customer data.
     * @return int|false Inserted ID or false on failure.
     */
    public function create(array $data): int|false
    {
        $allowed = [
            'full_name', 'email', 'phone', 'address', 'city', 'date_of_birth',
            'gender', 'blood_group', 'allergies', 'notes', 'loyalty_points', 'is_active',
        ];

        $fields      = [];
        $placeholders = [];
        $params      = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[]           = $col;
                $placeholders[]    = ":{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fieldsStr      = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);

        $sql  = "INSERT INTO customers ({$fieldsStr}) VALUES ({$placeholdersStr})";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($params)) {
            return (int) $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update a customer record.
     *
     * @param  int   $id   Customer ID.
     * @param  array $data Data to update.
     * @return bool  True on success.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'full_name', 'email', 'phone', 'address', 'city', 'date_of_birth',
            'gender', 'blood_group', 'allergies', 'notes', 'loyalty_points', 'is_active',
        ];

        $setClauses = [];
        $params = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[]        = "{$col} = :u_{$col}";
                $params[":u_{$col}"] = $data[$col];
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setStr = implode(', ', $setClauses);
        $sql    = "UPDATE customers SET {$setStr} WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a customer record.
     *
     * @param  int  $id Customer ID.
     * @return bool True on success.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM customers WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get purchase history for a customer.
     *
     * @param  int $customerId Customer ID.
     * @return array Array of sale records.
     */
    public function getPurchaseHistory(int $customerId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.invoice_number, s.total_amount, s.payment_method, s.payment_status, s.created_at,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count 
             FROM sales s 
             WHERE s.customer_id = :customer_id 
             ORDER BY s.created_at DESC'
        );
        $stmt->execute([':customer_id' => $customerId]);
        return $stmt->fetchAll();
    }

    /**
     * Add loyalty points to a customer based on purchase amount.
     *
     * @param  int   $customerId Customer ID.
     * @param  float $amount     Purchase amount.
     * @return bool  True on success.
     */
    public function addLoyaltyPoints(int $customerId, float $amount): bool
    {
        $points = (int) floor($amount / 1000); // 1 point per 1000 TZS

        if ($points <= 0) {
            return true; // Nothing to add
        }

        $stmt = $this->db->prepare(
            'UPDATE customers SET loyalty_points = loyalty_points + :points WHERE id = :id'
        );
        return $stmt->execute([':points' => $points, ':id' => $customerId]);
    }

    /**
     * Get total number of customers.
     *
     * @return int Total count.
     */
    public function getTotalCustomers(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS cnt FROM customers');
        $row  = $stmt->fetch();
        return $row !== false ? (int) $row['cnt'] : 0;
    }
}
