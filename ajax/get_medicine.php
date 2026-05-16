<?php
/**
 * MedWell Pharmacy - AJAX: Get Medicine Details
 *
 * Returns full details for a single medicine, including category
 * name and supplier name via JOINs.
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
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid medicine ID.']);
        exit;
    }

    // ─── Database Query ────────────────────────────────────────────────
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT m.*,
                mc.name AS category_name,
                s.name  AS supplier_name
         FROM medicines m
         LEFT JOIN medicine_categories mc ON m.category_id = mc.id
         LEFT JOIN suppliers s ON m.supplier_id = s.id
         WHERE m.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);

    $medicine = $stmt->fetch();

    if ($medicine === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Medicine not found.',
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => $medicine,
    ]);

} catch (PDOException $e) {
    error_log('get_medicine.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching medicine details.',
    ]);
} catch (Throwable $e) {
    error_log('get_medicine.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
