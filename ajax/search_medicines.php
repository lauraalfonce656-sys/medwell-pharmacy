<?php
/**
 * MedWell Pharmacy - AJAX: Search Medicines
 *
 * POS autocomplete endpoint for searching medicines by name,
 * generic_name, barcode, or batch_number. Only returns active
 * medicines with stock > 0.
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
    $query = trim($_GET['q'] ?? '');
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

    // Clamp limit to sane range
    if ($limit < 1) {
        $limit = 20;
    } elseif ($limit > 100) {
        $limit = 100;
    }

    // Require at least 2 characters for search
    if (mb_strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'data'    => [],
            'count'   => 0,
        ]);
        exit;
    }

    // ─── Database Query ────────────────────────────────────────────────
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT m.id, m.name, m.generic_name, m.batch_number, m.price,
                m.quantity, mc.name AS category, m.expiry_date
         FROM medicines m
         LEFT JOIN medicine_categories mc ON m.category_id = mc.id
         WHERE m.is_active = 1
           AND m.quantity > 0
           AND (
               m.name LIKE :q_name
               OR m.generic_name LIKE :q_generic
               OR m.barcode LIKE :q_barcode
               OR m.batch_number LIKE :q_batch
           )
         ORDER BY m.name ASC
         LIMIT :limit'
    );

    $searchParam = "%{$query}%";
    $stmt->bindValue(':q_name',    $searchParam);
    $stmt->bindValue(':q_generic', $searchParam);
    $stmt->bindValue(':q_barcode', $searchParam);
    $stmt->bindValue(':q_batch',   $searchParam);
    $stmt->bindValue(':limit',     $limit, PDO::PARAM_INT);
    $stmt->execute();

    $results = $stmt->fetchAll();

    // ─── Format Response ───────────────────────────────────────────────
    echo json_encode([
        'success' => true,
        'data'    => $results,
        'count'   => count($results),
    ]);

} catch (PDOException $e) {
    error_log('search_medicines.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching medicines.',
    ]);
} catch (Throwable $e) {
    error_log('search_medicines.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
