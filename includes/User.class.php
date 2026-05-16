<?php
/**
 * MedWell Pharmacy - User Class
 * 
 * User management with authentication, CRUD, and role-based operations.
 * PHP 8.0+
 */

declare(strict_types=1);

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Authenticate a user by username and password.
     *
     * @param  string $username The username.
     * @param  string $password The plain-text password.
     * @return array|null User data on success, null on failure.
     */
    public function login(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, full_name, email, password_hash, role, is_active 
             FROM users 
             WHERE username = :username 
             LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        if ((int) $user['is_active'] !== 1) {
            return null;
        }

        if (!verifyPassword($password, $user['password_hash'])) {
            return null;
        }

        // Update last login timestamp
        $this->updateLastLogin((int) $user['id']);

        // Return user data without password hash
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Get a single user by ID.
     *
     * @param  int $id User ID.
     * @return array|null User record or null.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, full_name, email, role, is_active, last_login, created_at, updated_at 
             FROM users 
             WHERE id = :id 
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Get all users.
     *
     * @return array Array of user records.
     */
    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, username, full_name, email, role, is_active, last_login, created_at 
             FROM users 
             ORDER BY full_name ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Create a new user.
     *
     * @param  array $data User data (must include 'password' as plain text).
     * @return int|false Inserted ID or false on failure.
     */
    public function create(array $data): int|false
    {
        $allowed = [
            'username', 'full_name', 'email', 'role', 'is_active',
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

        // Handle password separately for hashing
        if (isset($data['password']) && $data['password'] !== '') {
            $fields[]           = 'password_hash';
            $placeholders[]    = ':password_hash';
            $params[':password_hash'] = hashPassword($data['password']);
        }

        if (empty($fields)) {
            return false;
        }

        $fieldsStr      = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);

        $sql  = "INSERT INTO users ({$fieldsStr}) VALUES ({$placeholdersStr})";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($params)) {
            return (int) $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update a user record.
     *
     * @param  int   $id   User ID.
     * @param  array $data Data to update.
     * @return bool  True on success.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'username', 'full_name', 'email', 'role', 'is_active',
        ];

        $setClauses = [];
        $params = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $setClauses[]        = "{$col} = :u_{$col}";
                $params[":u_{$col}"] = $data[$col];
            }
        }

        // Handle password update separately
        if (isset($data['password']) && $data['password'] !== '') {
            $setClauses[]            = 'password_hash = :u_password_hash';
            $params[':u_password_hash'] = hashPassword($data['password']);
        }

        if (empty($setClauses)) {
            return false;
        }

        $setStr = implode(', ', $setClauses);
        $sql    = "UPDATE users SET {$setStr}, updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a user record.
     *
     * @param  int  $id User ID.
     * @return bool True on success.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Update a user's password after verifying the current password.
     *
     * @param  int    $id              User ID.
     * @param  string $currentPassword Current plain-text password.
     * @param  string $newPassword     New plain-text password.
     * @return bool   True on success.
     */
    public function updatePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        // Fetch current password hash
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return false;
        }

        // Verify current password
        if (!verifyPassword($currentPassword, $row['password_hash'])) {
            return false;
        }

        // Update to new password
        $newHash = hashPassword($newPassword);
        $updateStmt = $this->db->prepare(
            'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id'
        );
        return $updateStmt->execute([':hash' => $newHash, ':id' => $id]);
    }

    /**
     * Update the last login timestamp for a user.
     *
     * @param  int  $id User ID.
     * @return bool True on success.
     */
    public function updateLastLogin(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get users filtered by role.
     *
     * @param  string $role Role to filter by.
     * @return array Array of user records.
     */
    public function getByRole(string $role): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, full_name, email, role, is_active, last_login, created_at 
             FROM users 
             WHERE role = :role 
             ORDER BY full_name ASC'
        );
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    }
}
