<?php
/**
 * MedWell Pharmacy - AJAX: Mark Notification as Read
 *
 * Marks a specific notification as read for the current user.
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ─── CSRF Token Validation ────────────────────────────────────────────────
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page.']);
    exit;
}

try {
    // ─── Input Parameters ──────────────────────────────────────────────
    $notificationId = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;

    if ($notificationId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
        exit;
    }

    // ─── Update Notification ───────────────────────────────────────────
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE id = :id AND user_id = :user_id'
    );
    $stmt->execute([
        ':id'      => $notificationId,
        ':user_id' => getCurrentUserId(),
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or already read.',
        ]);
        exit;
    }

    // ─── Regenerate CSRF Token ─────────────────────────────────────────
    regenerateCsrfToken();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('mark_notification_read.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the notification.',
    ]);
} catch (Throwable $e) {
    error_log('mark_notification_read.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
