<?php
/**
 * MedWell Pharmacy - General Utility Functions
 * 
 * Sanitization, formatting, flash messages, file uploads, 
 * inventory logging, and CSRF helpers.
 * PHP 8.0+
 */

declare(strict_types=1);

// ─── Sanitization ───────────────────────────────────────────────────────────

/**
 * Sanitize data for safe output.
 *
 * @param  mixed $data Input data (string or array).
 * @return mixed Sanitized data.
 */
function sanitize(mixed $data): mixed
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }

    $data = trim((string) $data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

// ─── Redirection ────────────────────────────────────────────────────────────

/**
 * Redirect to a given URL and terminate.
 *
 * @param string $url The target URL.
 */
function redirect(string $url): void
{
    if (!headers_sent()) {
        header("Location: {$url}");
        exit;
    }

    // Fallback for when headers are already sent
    echo "<script>window.location.href='{$url}';</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url={$url}'></noscript>";
    exit;
}

// ─── Flash Messages ─────────────────────────────────────────────────────────

/**
 * Set a flash message in the session.
 *
 * @param string $key     Message key (e.g. 'success', 'error').
 * @param string $message The message text.
 * @param string $type    Message type: 'success', 'error', 'warning', 'info'.
 */
function flashMessage(string $key, string $message, string $type = 'info'): void
{
    startSession();
    $_SESSION['flash_messages'][$key] = [
        'message' => $message,
        'type'    => $type,
    ];
}

/**
 * Get and clear a flash message from the session.
 *
 * @param  string $key The message key.
 * @return array|null  The flash message array or null.
 */
function getFlashMessage(string $key): ?array
{
    startSession();

    if (isset($_SESSION['flash_messages'][$key])) {
        $message = $_SESSION['flash_messages'][$key];
        unset($_SESSION['flash_messages'][$key]);
        return $message;
    }

    return null;
}

// ─── Currency Formatting ────────────────────────────────────────────────────

/**
 * Format a number as currency.
 *
 * @param  float $amount The amount to format.
 * @return string Formatted currency string.
 */
function formatCurrency(float $amount): string
{
    return number_format($amount, 2, '.', ',') . ' ' . CURRENCY;
}

// ─── Date Formatting ────────────────────────────────────────────────────────

/**
 * Format a date string to a human-readable format.
 *
 * @param  string $date Date string (Y-m-d or any parseable format).
 * @return string Formatted date.
 */
function formatDate(string $date): string
{
    try {
        $dt = new DateTime($date);
        return $dt->format('d M Y');
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format a datetime string to a human-readable format.
 *
 * @param  string $datetime Datetime string.
 * @return string Formatted datetime.
 */
function formatDateTime(string $datetime): string
{
    try {
        $dt = new DateTime($datetime);
        return $dt->format('d M Y, H:i');
    } catch (Exception $e) {
        return $datetime;
    }
}

// ─── Invoice Number Generation ──────────────────────────────────────────────

/**
 * Generate a unique invoice number: INV-YYYYMMDD-XXXX
 *
 * @return string The generated invoice number.
 */
function generateInvoiceNumber(): string
{
    $datePart = date('Ymd');

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS today_count FROM sales WHERE DATE(created_at) = CURDATE()"
        );
        $stmt->execute();
        $row = $stmt->fetch();
        $count = ($row !== false ? (int) $row['today_count'] : 0) + 1;
    } catch (PDOException $e) {
        // Fallback: use random suffix
        $count = random_int(1, 9999);
    }

    return sprintf('INV-%s-%04d', $datePart, $count);
}

// ─── Expiry Helpers ─────────────────────────────────────────────────────────

/**
 * Calculate the number of days until a given expiry date.
 *
 * @param  string $date The expiry date.
 * @return int Number of days (negative if already expired).
 */
function calculateExpiry(string $date): int
{
    try {
        $expiry  = new DateTime($date);
        $today   = new DateTime('today');
        $diff    = $today->diff($expiry);
        return $diff->invert ? -$diff->days : $diff->days;
    } catch (Exception $e) {
        return -1;
    }
}

/**
 * Check if a date is expiring within a given number of days.
 *
 * @param  string $date The expiry date.
 * @param  int    $days Threshold in days (default 30).
 * @return bool True if expiring soon or already expired.
 */
function isExpiringSoon(string $date, int $days = 30): bool
{
    $remaining = calculateExpiry($date);
    return $remaining >= 0 && $remaining <= $days;
}

// ─── Stock Helpers ──────────────────────────────────────────────────────────

/**
 * Check if stock is at or below the minimum level.
 *
 * @param  int $quantity Current quantity.
 * @param  int $minLevel Minimum stock level.
 * @return bool True if stock is low.
 */
function isLowStock(int $quantity, int $minLevel): bool
{
    return $quantity <= $minLevel;
}

// ─── Settings ───────────────────────────────────────────────────────────────

/**
 * Fetch all settings from the settings table as key-value pairs.
 *
 * @return array Associative array of settings.
 */
function getSettings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->query('SELECT setting_key, setting_value FROM settings');
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log('getSettings error: ' . $e->getMessage());
        $settings = [];
    }

    return $settings;
}

/**
 * Update a setting by key.
 *
 * @param  string $key   Setting key.
 * @param  string $value New value.
 * @return bool True on success.
 */
function updateSetting(string $key, string $value): bool
{
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO settings (setting_key, setting_value) 
             VALUES (:key, :value) 
             ON DUPLICATE KEY UPDATE setting_value = :value2'
        );
        $stmt->execute([
            ':key'   => $key,
            ':value' => $value,
            ':value2' => $value,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('updateSetting error: ' . $e->getMessage());
        return false;
    }
}

// ─── Inventory Logging ──────────────────────────────────────────────────────

/**
 * Log an inventory movement.
 *
 * @param int    $medicineId Medicine ID.
 * @param string $type       Movement type: 'purchase', 'sale', 'adjustment', 'return', 'expired'.
 * @param int    $qtyBefore  Quantity before the movement.
 * @param int    $qtyAfter   Quantity after the movement.
 * @param string $refType    Reference type: 'sale', 'purchase', 'adjustment'.
 * @param int|null $refId    Reference ID.
 * @param string $notes      Optional notes.
 */
function logInventory(
    int $medicineId,
    string $type,
    int $qtyBefore,
    int $qtyAfter,
    string $refType = '',
    ?int $refId = null,
    string $notes = ''
): void {
    try {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO inventory_logs 
             (medicine_id, movement_type, quantity_before, quantity_after, reference_type, reference_id, notes, created_by, created_at) 
             VALUES 
             (:medicine_id, :type, :qty_before, :qty_after, :ref_type, :ref_id, :notes, :created_by, NOW())'
        );
        $stmt->execute([
            ':medicine_id'  => $medicineId,
            ':type'         => $type,
            ':qty_before'   => $qtyBefore,
            ':qty_after'    => $qtyAfter,
            ':ref_type'     => $refType,
            ':ref_id'       => $refId,
            ':notes'        => $notes,
            ':created_by'   => getCurrentUserId(),
        ]);
    } catch (PDOException $e) {
        error_log('logInventory error: ' . $e->getMessage());
    }
}

// ─── Table Aggregation Helpers ──────────────────────────────────────────────

/**
 * Count rows in a table with optional conditions.
 *
 * @param  string $table      Table name (whitelist in calling code).
 * @param  array  $conditions Optional WHERE conditions as ['column' => value].
 * @return int Row count.
 */
function countTable(string $table, array $conditions = []): int
{
    try {
        $db    = Database::getInstance()->getConnection();
        $where = '';
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                $clauses[]           = "{$col} = :w_{$col}";
                $params[":w_{$col}"] = $val;
            }
            $where = ' WHERE ' . implode(' AND ', $clauses);
        }

        // Only allow alphanumeric + underscore in table name to prevent injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException("Invalid table name: {$table}");
        }

        $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM {$table}{$where}");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? (int) $row['cnt'] : 0;
    } catch (PDOException $e) {
        error_log('countTable error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Sum a column in a table with optional conditions.
 *
 * @param  string $table      Table name.
 * @param  string $column     Column to sum.
 * @param  array  $conditions Optional WHERE conditions.
 * @return float Sum value.
 */
function sumColumn(string $table, string $column, array $conditions = []): float
{
    try {
        $db     = Database::getInstance()->getConnection();
        $where  = '';
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                $clauses[]           = "{$col} = :w_{$col}";
                $params[":w_{$col}"] = $val;
            }
            $where = ' WHERE ' . implode(' AND ', $clauses);
        }

        // Validate identifiers
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException("Invalid table name: {$table}");
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Invalid column name: {$column}");
        }

        $stmt = $db->prepare("SELECT COALESCE(SUM({$column}), 0) AS total FROM {$table}{$where}");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? (float) $row['total'] : 0.0;
    } catch (PDOException $e) {
        error_log('sumColumn error: ' . $e->getMessage());
        return 0.0;
    }
}

// ─── File Upload ────────────────────────────────────────────────────────────

/**
 * Upload a file with validation.
 *
 * @param  array  $file         The $_FILES entry.
 * @param  string $destination  Destination directory (absolute path).
 * @param  array  $allowedTypes Allowed MIME types (e.g. ['image/jpeg', 'image/png']).
 * @return array  Result with 'success', 'filename', 'path', 'error' keys.
 */
function uploadFile(array $file, string $destination, array $allowedTypes = []): array
{
    // Validate upload
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded or upload attack detected.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes, true)) {
        return ['success' => false, 'error' => 'File type not allowed. Detected: ' . $mimeType];
    }

    // Create directory if it doesn't exist
    if (!is_dir($destination) && !mkdir($destination, 0755, true)) {
        return ['success' => false, 'error' => 'Failed to create destination directory.'];
    }

    // Generate a safe unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName  = bin2hex(random_bytes(16)) . '.' . $extension;
    $filePath  = rtrim($destination, '/') . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }

    return [
        'success'  => true,
        'filename' => $safeName,
        'path'     => $filePath,
        'mime'     => $mimeType,
        'size'     => $file['size'],
    ];
}

// ─── Slugify ────────────────────────────────────────────────────────────────

/**
 * Convert text to a URL-safe slug.
 *
 * @param  string $text The input text.
 * @return string The slugified text.
 */
function slugify(string $text): string
{
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return strtolower($text);
}

// ─── CSRF Field Helpers ────────────────────────────────────────────────────

/**
 * Output a hidden CSRF token input field.
 *
 * @return string HTML hidden input.
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate the CSRF token from the current request.
 *
 * @param  string|null $token Optional token; defaults to POST value.
 * @return bool True if valid.
 */
function validateCsrf(?string $token = null): bool
{
    return validateCsrfToken($token);
}
