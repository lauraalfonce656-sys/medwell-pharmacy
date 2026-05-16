<?php
/**
 * MedWell Pharmacy - Supplier Class
 * 
 * CRUD operations for suppliers with related medicine lookups.
 * PHP 8.0+
 */

declare(strict_types=1);

class Supplier
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all suppliers with optional search and pagination.
     *
     * @param  string $search Search term (name, email, phone).
     * @param  int    $limit  Rows per page.
     * @param  int    $offset Offset.
     * @return array  Supplier records.
     */
    public function getAll(string $search = '', int $limit = 50, int $offset = 0): array
    {
        $sql    = 'SELECT * FROM suppliers WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search OR email LIKE :search2 OR phone LIKE :search3 OR contact_person LIKE :search4)';
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
            $params[':search4'] = "%{$search}%";
        }

        $sql .= ' ORDER BY name ASC LIMIT :limit OFFSET :offset';
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
     * Get a single supplier by ID.
     *
     * @param  int $id Supplier ID.
     * @return array|null Supplier record or null.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM suppliers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new supplier.
     *
     * @param  array $data Supplier data.
     * @return int|false Inserted ID or false on failure.
     */
    public function create(array $data): int|false
    {
        $allowed = [
            'name', 'contact_person', 'email', 'phone', 'address', 'city',
            'tax_identification_number', 'payment_terms', 'notes', 'is_active',
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

        $sql  = "INSERT INTO suppliers ({$fieldsStr}) VALUES ({$placeholdersStr})";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($params)) {
            return (int) $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update a supplier record.
     *
     * @param  int   $id   Supplier ID.
     * @param  array $data Data to update.
     * @return bool  True on success.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'name', 'contact_person', 'email', 'phone', 'address', 'city',
            'tax_identification_number', 'payment_terms', 'notes', 'is_active',
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
        $sql    = "UPDATE suppliers SET {$setStr} WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a supplier record.
     *
     * @param  int  $id Supplier ID.
     * @return bool True on success.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM suppliers WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all medicines supplied by a specific supplier.
     *
     * @param  int $supplierId Supplier ID.
     * @return array Array of medicine records.
     */
    public function getSupplierMedicines(int $supplierId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, c.name AS category_name 
             FROM medicines m 
             LEFT JOIN categories c ON m.category_id = c.id 
             WHERE m.supplier_id = :supplier_id 
             ORDER BY m.name ASC'
        );
        $stmt->execute([':supplier_id' => $supplierId]);
        return $stmt->fetchAll();
    }

    /**
     * Get total number of suppliers.
     *
     * @return int Total count.
     */
    public function getTotalSuppliers(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS cnt FROM suppliers');
        $row  = $stmt->fetch();
        return $row !== false ? (int) $row['cnt'] : 0;
    }
}
