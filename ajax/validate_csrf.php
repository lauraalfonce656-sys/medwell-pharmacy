<?php
/**
 * MedWell Pharmacy - AJAX: Validate CSRF Token
 *
 * Validates the provided CSRF token and returns whether it is valid,
 * along with a fresh token for subsequent requests. Used by the
 * frontend to verify token state without a full page reload.
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

try {
    // ─── Input Parameters ──────────────────────────────────────────────
    $currentToken = $_POST['current_token'] ?? '';

    // ─── Validate the Token ────────────────────────────────────────────
    $isValid = validateCsrfToken($currentToken);

    // ─── Generate Fresh Token ──────────────────────────────────────────
    // Always provide a fresh token regardless of validity,
    // so the frontend can update its stored token.
    $newToken = generateCsrfToken();

    echo json_encode([
        'success'   => true,
        'valid'     => $isValid,
        'new_token' => $newToken,
    ]);

} catch (Throwable $e) {
    error_log('validate_csrf.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
    ]);
}
