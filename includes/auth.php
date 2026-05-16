<?php
/**
 * MedWell Pharmacy - Authentication Utilities
 * 
 * Session management, login/logout, role checks, and password hashing.
 * PHP 8.0+
 */

declare(strict_types=1);

/**
 * Start a secure session if not already active.
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if a user is currently logged in.
 *
 * @return bool True if logged in, false otherwise.
 */
function isLoggedIn(): bool
{
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require the user to be logged in; redirect to login page if not.
 *
 * @param string $redirectUrl URL to redirect to on failure.
 */
function requireLogin(string $redirectUrl = '/login.php'): void
{
    if (!isLoggedIn()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        header("Location: {$redirectUrl}");
        exit;
    }
}

/**
 * Check if the currently logged-in user has the specified role.
 *
 * @param  string $role The role to check against.
 * @return bool True if user has the role, false otherwise.
 */
function hasRole(string $role): bool
{
    startSession();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Require the user to have a specific role; redirect or deny access if not.
 *
 * @param string $role        The required role.
 * @param string $redirectUrl URL to redirect to on failure.
 */
function requireRole(string $role, string $redirectUrl = '/index.php'): void
{
    requireLogin();

    if (!hasRole($role)) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'Access denied. Insufficient permissions.';
        header("Location: {$redirectUrl}");
        exit;
    }
}

/**
 * Log a user in by setting session data.
 *
 * @param int $userId   The user's ID.
 * @param string $username The user's username.
 * @param string $role     The user's role.
 */
function login(int $userId, string $username, string $role): void
{
    startSession();

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']       = $userId;
    $_SESSION['username']      = $username;
    $_SESSION['user_role']     = $role;
    $_SESSION['login_time']    = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address']    = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Generate a fresh CSRF token
    $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

/**
 * Log the current user out by destroying the session.
 */
function logout(): void
{
    startSession();

    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie if it exists
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();
}

/**
 * Get the currently logged-in user's data from the database.
 *
 * @return array|null User data array or null if not logged in.
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('getCurrentUser error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get the currently logged-in user's ID.
 *
 * @return int|null User ID or null.
 */
function getCurrentUserId(): ?int
{
    startSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Hash a password using bcrypt.
 *
 * @param  string $password The plain-text password.
 * @return string The hashed password.
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against its hash.
 *
 * @param  string $password The plain-text password.
 * @param  string $hash     The stored hash.
 * @return bool True if the password matches, false otherwise.
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check session timeout (optional security feature).
 *
 * @param  int $timeoutSeconds Number of seconds before timeout (default 30 minutes).
 * @return bool True if session is still valid, false if timed out.
 */
function checkSessionTimeout(int $timeoutSeconds = 1800): bool
{
    startSession();

    if (!isset($_SESSION['last_activity'])) {
        return false;
    }

    if (time() - $_SESSION['last_activity'] > $timeoutSeconds) {
        logout();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}
