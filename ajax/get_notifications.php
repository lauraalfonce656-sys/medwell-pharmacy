<?php
/**
 * MedWell Pharmacy - AJAX: Get Notifications
 *
 * Returns the current user's notifications, ordered by newest first.
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
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

    // Clamp limit to sane range
    if ($limit < 1) {
        $limit = 10;
    } elseif ($limit > 100) {
        $limit = 100;
    }

    // ─── Database Query ────────────────────────────────────────────────
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, title, message, type, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':user_id', getCurrentUserId(), PDO::PARAM_INT);
    $stmt->bindValue(':limit',   $limit, PDO::PARAM_INT);
    $stmt->execute();

    $notifications = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $notifications,
        'count'   => count($notifications),
    ]);

} catch (PDOException $e) {
    error_log('get_notifications.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching notifications.',
    ]);
} catch (Throwable $e) {
    error_log('get_notifications.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
