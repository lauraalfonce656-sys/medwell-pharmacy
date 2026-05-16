<?php
/**
 * MedWell Pharmacy - Notification Class
 * 
 * User notification management with auto-generation for
 * low stock and expiring medicine alerts.
 * PHP 8.0+
 */

declare(strict_types=1);

class Notification
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new notification.
     *
     * @param  int    $userId  Target user ID.
     * @param  string $title   Notification title.
     * @param  string $message Notification message.
     * @param  string $type    Type: 'info', 'warning', 'danger', 'success'.
     * @return int|false Inserted ID or false on failure.
     */
    public function create(int $userId, string $title, string $message, string $type = 'info'): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
             VALUES (:user_id, :title, :message, :type, 0, NOW())'
        );

        if ($stmt->execute([
            ':user_id' => $userId,
            ':title'   => $title,
            ':message' => $message,
            ':type'    => $type,
        ])) {
            return (int) $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Get notifications for a user.
     *
     * @param  int $userId User ID.
     * @param  int $limit  Max number of notifications to return.
     * @return array Notification records.
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :lim'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark a single notification as read.
     *
     * @param  int  $id      Notification ID.
     * @param  int  $userId  User ID (for authorization check).
     * @return bool True on success.
     */
    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE id = :id AND user_id = :user_id'
        );
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param  int  $userId User ID.
     * @return bool True on success.
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE user_id = :user_id AND is_read = 0'
        );
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Get count of unread notifications for a user.
     *
     * @param  int $userId User ID.
     * @return int Unread count.
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM notifications 
             WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row !== false ? (int) $row['cnt'] : 0;
    }

    /**
     * Delete a notification.
     *
     * @param  int  $id     Notification ID.
     * @param  int  $userId User ID (for authorization).
     * @return bool True on success.
     */
    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM notifications WHERE id = :id AND user_id = :user_id'
        );
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    /**
     * Automatically generate low-stock notifications for all admin/manager users.
     * Only creates notifications for medicines not already notified about today.
     *
     * @return int Number of notifications created.
     */
    public function checkLowStock(): int
    {
        $created = 0;

        try {
            // Get low-stock medicines
            $medStmt = $this->db->prepare(
                'SELECT id, name, quantity, min_stock_level, batch_number 
                 FROM medicines 
                 WHERE is_active = 1 
                   AND quantity <= min_stock_level'
            );
            $medStmt->execute();
            $lowStockMeds = $medStmt->fetchAll();

            if (empty($lowStockMeds)) {
                return 0;
            }

            // Get all admin and manager users
            $userStmt = $this->db->prepare(
                "SELECT id FROM users WHERE role IN ('admin', 'manager') AND is_active = 1"
            );
            $userStmt->execute();
            $users = $userStmt->fetchAll();

            if (empty($users)) {
                return 0;
            }

            // Check for existing low-stock notifications today to avoid duplicates
            $checkStmt = $this->db->prepare(
                "SELECT id FROM notifications 
                 WHERE type = 'low_stock' 
                   AND DATE(created_at) = CURDATE() 
                   AND message LIKE :med_pattern 
                 LIMIT 1"
            );

            foreach ($lowStockMeds as $med) {
                // Check if we already notified about this medicine today
                $checkStmt->execute([':med_pattern' => '%' . $med['name'] . '%']);
                $existing = $checkStmt->fetch();

                if ($existing !== false) {
                    continue; // Already notified today
                }

                $title   = 'Low Stock Alert';
                $message = sprintf(
                    '%s (Batch: %s) — Current stock: %d, Minimum level: %d',
                    $med['name'],
                    $med['batch_number'] ?? 'N/A',
                    $med['quantity'],
                    $med['min_stock_level']
                );

                foreach ($users as $user) {
                    if ($this->create((int) $user['id'], $title, $message, 'low_stock') !== false) {
                        $created++;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Notification::checkLowStock error: ' . $e->getMessage());
        }

        return $created;
    }

    /**
     * Automatically generate expiry notifications for medicines expiring soon.
     * Only creates notifications for medicines not already notified about today.
     *
     * @param  int $days Days threshold for "expiring soon" (default 30).
     * @return int Number of notifications created.
     */
    public function checkExpiry(int $days = 30): int
    {
        $created = 0;

        try {
            // Get expiring medicines
            $medStmt = $this->db->prepare(
                'SELECT id, name, quantity, expiry_date, batch_number 
                 FROM medicines 
                 WHERE is_active = 1 
                   AND expiry_date IS NOT NULL 
                   AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)'
            );
            $medStmt->bindValue(':days', $days, PDO::PARAM_INT);
            $medStmt->execute();
            $expiringMeds = $medStmt->fetchAll();

            if (empty($expiringMeds)) {
                return 0;
            }

            // Get all admin and manager users
            $userStmt = $this->db->prepare(
                "SELECT id FROM users WHERE role IN ('admin', 'manager') AND is_active = 1"
            );
            $userStmt->execute();
            $users = $userStmt->fetchAll();

            if (empty($users)) {
                return 0;
            }

            // Check for existing expiry notifications today
            $checkStmt = $this->db->prepare(
                "SELECT id FROM notifications 
                 WHERE type = 'expiry' 
                   AND DATE(created_at) = CURDATE() 
                   AND message LIKE :med_pattern 
                 LIMIT 1"
            );

            foreach ($expiringMeds as $med) {
                // Check if already notified today
                $checkStmt->execute([':med_pattern' => '%' . $med['name'] . '%']);
                $existing = $checkStmt->fetch();

                if ($existing !== false) {
                    continue;
                }

                $daysLeft = calculateExpiry($med['expiry_date']);
                $title    = $daysLeft <= 0 ? 'Medicine Expired' : 'Medicine Expiring Soon';
                $message  = sprintf(
                    '%s (Batch: %s) — Expires: %s (%s), Stock: %d',
                    $med['name'],
                    $med['batch_number'] ?? 'N/A',
                    formatDate($med['expiry_date']),
                    $daysLeft <= 0 ? 'ALREADY EXPIRED' : "{$daysLeft} days left",
                    $med['quantity']
                );

                $notifType = $daysLeft <= 0 ? 'danger' : 'warning';

                foreach ($users as $user) {
                    if ($this->create((int) $user['id'], $title, $message, $notifType) !== false) {
                        $created++;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Notification::checkExpiry error: ' . $e->getMessage());
        }

        return $created;
    }
}
