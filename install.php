<?php
/**
 * MedWell Pharmacy - Installation Wizard
 * 
 * This script guides you through the setup process.
 * DELETE THIS FILE after installation for security.
 */

session_start();

// Configuration
define('MW_VERSION', '1.0.0');
define('MW_MIN_PHP', '7.4.0');
define('MW_INSTALL_LOCK', __DIR__ . '/config/install.lock');

// Check if already installed
if (file_exists(MW_INSTALL_LOCK)) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Already Installed</title>
    <style>body{font-family:Segoe UI,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f0f4f8;color:#334155}
    .msg{text-align:center;padding:3rem;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:500px}
    h1{color:#0f766e;font-size:1.5rem}p{color:#64748b;line-height:1.6}
    .btn{display:inline-block;margin-top:1rem;padding:.75rem 2rem;background:#0f766e;color:#fff;text-decoration:none;border-radius:8px;font-weight:600}</style></head>
    <body><div class="msg"><h1>✓ MedWell Pharmacy Already Installed</h1><p>This system has already been configured. For security, install.php should be removed.</p>
    <a href="index.php" class="btn">Go to Homepage</a></div></body></html>');
}

// Step management
$step = isset($_POST['step']) ? max(1, min(6, (int)$_POST['step'])) : 1;
$errors = [];
$success = false;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Validate database connection
            $db_host = trim($_POST['db_host'] ?? 'localhost');
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';
            $db_port = trim($_POST['db_port'] ?? '3306');

            if (empty($db_name) || empty($db_user)) {
                $errors[] = 'Database name and user are required.';
            } else {
                try {
                    $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
                    $pdo = new PDO($dsn, $db_user, $db_pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    // Check if database exists
                    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$db_name}'");
                    if (!$stmt->fetch()) {
                        $pdo->exec("CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    $pdo->exec("USE `{$db_name}`");
                    $_SESSION['db_config'] = compact('db_host', 'db_name', 'db_user', 'db_pass', 'db_port');
                } catch (PDOException $e) {
                    $errors[] = 'Database connection failed: ' . $e->getMessage();
                    $step = 2;
                }
            }
            if (empty($errors) && !isset($_SESSION['db_config'])) {
                $step = 2;
            } elseif (empty($errors)) {
                $step = 3;
            }
            break;

        case 3:
            // Import SQL file
            if (!isset($_SESSION['db_config'])) {
                $step = 2;
                break;
            }
            $sql_file = __DIR__ . '/medwell_pharmacy.sql';
            if (!file_exists($sql_file)) {
                $errors[] = 'SQL file not found: medwell_pharmacy.sql. Please upload it to the root directory.';
                $step = 3;
            } else {
                try {
                    $c = $_SESSION['db_config'];
                    $dsn = "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $sql = file_get_contents($sql_file);
                    // Split on semicolons followed by newlines, handle multi-line statements
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    $statements = array_filter(
                        array_map('trim', preg_split('/;\s*\n/', $sql)),
                        fn($s) => !empty($s) && !preg_match('/^\s*--/', $s) && !preg_match('/^\s*#/', $s)
                    );
                    foreach ($statements as $stmt) {
                        if (!empty(trim($stmt))) {
                            $pdo->exec($stmt);
                        }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                    $_SESSION['sql_imported'] = true;
                    $step = 4;
                } catch (PDOException $e) {
                    $errors[] = 'SQL import failed: ' . $e->getMessage();
                    $step = 3;
                }
            }
            break;

        case 4:
            // Create admin account
            $admin_username = trim($_POST['admin_username'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_confirm = $_POST['admin_confirm'] ?? '';
            $admin_fullname = trim($_POST['admin_fullname'] ?? 'Administrator');

            if (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
                $errors[] = 'All admin fields are required.';
            } elseif (strlen($admin_username) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            } elseif (strlen($admin_password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } elseif ($admin_password !== $admin_confirm) {
                $errors[] = 'Passwords do not match.';
            } elseif (!preg_match('/[A-Z]/', $admin_password) || !preg_match('/[0-9]/', $admin_password)) {
                $errors[] = 'Password must contain at least one uppercase letter and one number.';
            } else {
                $_SESSION['admin_config'] = compact('admin_username', 'admin_email', 'admin_password', 'admin_fullname');
                $step = 5;
            }
            if (!empty($errors)) {
                $step = 4;
            }
            break;

        case 5:
            // Site configuration
            $site_name = trim($_POST['site_name'] ?? 'MedWell Pharmacy');
            $site_timezone = trim($_POST['site_timezone'] ?? 'UTC');
            $site_currency = trim($_POST['site_currency'] ?? 'USD');
            $site_currency_symbol = trim($_POST['site_currency_symbol'] ?? '$');

            if (empty($site_name)) {
                $errors[] = 'Pharmacy name is required.';
            } else {
                $_SESSION['site_config'] = compact('site_name', 'site_timezone', 'site_currency', 'site_currency_symbol');
                // Everything is ready - write config and create admin
                $result = writeConfigAndFinalize();
                if ($result === true) {
                    $step = 6;
                    $success = true;
                } else {
                    $errors[] = $result;
                    $step = 5;
                }
            }
            break;

        case 6:
            // Delete install.php
            if (isset($_POST['delete_install'])) {
                @unlink(__FILE__);
                header('Location: index.php');
                exit;
            }
            break;
    }
}

/**
 * Write configuration and create admin account
 */
function writeConfigAndFinalize() {
    $db = $_SESSION['db_config'] ?? null;
    $admin = $_SESSION['admin_config'] ?? null;
    $site = $_SESSION['site_config'] ?? null;

    if (!$db || !$admin || !$site) {
        return 'Missing configuration data. Please restart the installation.';
    }

    // Write config.php
    $config_dir = __DIR__ . '/config';
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    $hash = password_hash($admin['admin_password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $config_content = "<?php\n";
    $config_content .= "/**\n * MedWell Pharmacy - Configuration File\n * Generated by Installer on " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT MANUALLY UNLESS YOU KNOW WHAT YOU ARE DOING\n */\n\n";
    $config_content .= "// Database Configuration\n";
    $config_content .= "define('DB_HOST', '" . addslashes($db['db_host']) . "');\n";
    $config_content .= "define('DB_PORT', '" . addslashes($db['db_port']) . "');\n";
    $config_content .= "define('DB_NAME', '" . addslashes($db['db_name']) . "');\n";
    $config_content .= "define('DB_USER', '" . addslashes($db['db_user']) . "');\n";
    $config_content .= "define('DB_PASS', '" . addslashes($db['db_pass']) . "');\n\n";
    $config_content .= "// Site Configuration\n";
    $config_content .= "define('SITE_NAME', '" . addslashes($site['site_name']) . "');\n";
    $config_content .= "define('SITE_TIMEZONE', '" . addslashes($site['site_timezone']) . "');\n";
    $config_content .= "define('SITE_CURRENCY', '" . addslashes($site['site_currency']) . "');\n";
    $config_content .= "define('SITE_CURRENCY_SYMBOL', '" . addslashes($site['site_currency_symbol']) . "');\n\n";
    $config_content .= "// Application\n";
    $config_content .= "define('MW_VERSION', '" . MW_VERSION . "');\n";
    $config_content .= "define('MW_INSTALLED', true);\n\n";
    $config_content .= "// Paths\n";
    $config_content .= "define('MW_ROOT', __DIR__ . '/..');\n";
    $config_content .= "define('MW_UPLOADS', MW_ROOT . '/uploads');\n\n";
    $config_content .= "// Security\n";
    $config_content .= "define('SESSION_LIFETIME', 3600); // 1 hour\n";
    $config_content .= "define('MAX_LOGIN_ATTEMPTS', 5);\n";
    $config_content .= "define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes\n\n";
    $config_content .= "// PDO Connection Function\n";
    $config_content .= "function getDB() {\n";
    $config_content .= "    static \$pdo = null;\n";
    $config_content .= "    if (\$pdo === null) {\n";
    $config_content .= "        try {\n";
    $config_content .= "            \$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';\n";
    $config_content .= "            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [\n";
    $config_content .= "                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
    $config_content .= "                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
    $config_content .= "                PDO::ATTR_EMULATE_PREPARES => false\n";
    $config_content .= "            ]);\n";
    $config_content .= "        } catch (PDOException \$e) {\n";
    $config_content .= "            die('Database connection failed. Please check your configuration.');\n";
    $config_content .= "        }\n";
    $config_content .= "    }\n";
    $config_content .= "    return \$pdo;\n";
    $config_content .= "}\n";

    if (file_put_contents($config_dir . '/config.php', $config_content) === false) {
        return 'Failed to write config/config.php. Check directory permissions.';
    }

    // Insert admin user into database
    try {
        $dsn = "mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['db_user'], $db['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Check if users table exists and insert admin
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$admin['admin_username'], $admin['admin_email']]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, 'admin', 'active', NOW())");
                $stmt->execute([$admin['admin_username'], $admin['admin_email'], $hash, $admin['admin_fullname']]);
            }
        } else {
            // Create minimal users table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                role ENUM('admin','pharmacist','cashier','staff') NOT NULL DEFAULT 'staff',
                status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
                last_login DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, 'admin', 'active', NOW())");
            $stmt->execute([$admin['admin_username'], $admin['admin_email'], $hash, $admin['admin_fullname']]);
        }

        // Update site settings if settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($stmt->fetch()) {
            $settings = [
                'site_name' => $site['site_name'],
                'timezone' => $site['site_timezone'],
                'currency' => $site['site_currency'],
                'currency_symbol' => $site['site_currency_symbol'],
            ];
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
        }

        // Create install.lock
        file_put_contents(MW_INSTALL_LOCK, date('Y-m-d H:i:s'));

        // Clear session data
        unset($_SESSION['db_config'], $_SESSION['admin_config'], $_SESSION['site_config'], $_SESSION['sql_imported']);

        return true;
    } catch (PDOException $e) {
        return 'Database error: ' . $e->getMessage();
    }
}

/**
 * Check system requirements
 */
function checkRequirements() {
    $checks = [];

    // PHP Version
    $checks['php_version'] = [
        'label' => 'PHP Version >= ' . MW_MIN_PHP,
        'status' => version_compare(PHP_VERSION, MW_MIN_PHP, '>='),
        'value' => PHP_VERSION
    ];

    // PDO MySQL
    $checks['pdo_mysql'] = [
        'label' => 'PDO MySQL Extension',
        'status' => extension_loaded('pdo_mysql'),
        'value' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing'
    ];

    // Session Support
    $checks['session'] = [
        'label' => 'Session Support',
        'status' => extension_loaded('session'),
        'value' => extension_loaded('session') ? 'Enabled' : 'Missing'
    ];

    // mbstring
    $checks['mbstring'] = [
        'label' => 'mbstring Extension',
        'status' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'Enabled' : 'Missing'
    ];

    // OpenSSL
    $checks['openssl'] = [
        'label' => 'OpenSSL Extension',
        'status' => extension_loaded('openssl'),
        'value' => extension_loaded('openssl') ? 'Enabled' : 'Missing'
    ];

    // JSON
    $checks['json'] = [
        'label' => 'JSON Extension',
        'status' => extension_loaded('json'),
        'value' => extension_loaded('json') ? 'Enabled' : 'Missing'
    ];

    // Config directory writable
    $checks['config_writable'] = [
        'label' => 'config/ Directory Writable',
        'status' => is_writable(__DIR__ . '/config') || mkdir(__DIR__ . '/config', 0755, true),
        'value' => is_writable(__DIR__ . '/config') ? 'Writable' : 'Not Writable'
    ];

    // Uploads directory writable
    $checks['uploads_writable'] = [
        'label' => 'uploads/ Directory Writable',
        'status' => is_writable(__DIR__ . '/uploads') || mkdir(__DIR__ . '/uploads', 0755, true),
        'value' => is_writable(__DIR__ . '/uploads') ? 'Writable' : 'Not Writable'
    ];

    return $checks;
}

$requirements = checkRequirements();
$allRequirementsMet = !in_array(false, array_column($requirements, 'status'), true);

// Timezone list
$timezones = DateTimeZone::listIdentifiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedWell Pharmacy - Installation Wizard</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        :root {
            --primary: #0f766e;
            --primary-light: #14b8a6;
            --primary-dark: #0d5d56;
            --primary-50: #f0fdfa;
            --primary-100: #ccfbf1;
            --accent: #059669;
            --bg: #f0f4f8;
            --card: #ffffff;
            --text: #1e293b;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --shadow: 0 4px 24px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 40px rgba(0,0,0,0.12);
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
            background-image: 
                radial-gradient(ellipse at top left, rgba(15,118,110,0.05) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(5,150,105,0.05) 0%, transparent 50%);
        }

        .installer-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .installer-header .logo {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 16px rgba(15,118,110,0.3);
        }

        .installer-header .logo svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        .installer-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .installer-header .subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 680px;
            overflow: hidden;
        }

        /* Stepper */
        .stepper {
            display: flex;
            background: var(--primary-50);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .step-item {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -50%;
            width: 100%;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }

        .step-item.active:not(:last-child)::after,
        .step-item.completed:not(:last-child)::after {
            background: var(--primary);
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            border: 2px solid var(--border);
            background: white;
            color: var(--text-secondary);
        }

        .step-item.active .step-circle {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 0 4px rgba(15,118,110,0.15);
        }

        .step-item.completed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step-label {
            position: absolute;
            bottom: -24px;
            font-size: 0.7rem;
            color: var(--text-secondary);
            white-space: nowrap;
            font-weight: 500;
        }

        .step-item.active .step-label { color: var(--primary); font-weight: 600; }
        .step-item.completed .step-label { color: var(--success); }

        /* Content */
        .card-body {
            padding: 2rem;
        }

        .card-body h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .card-body .desc {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-size: 0.92rem;
        }

        /* Requirements table */
        .req-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1.5rem;
        }

        .req-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: var(--primary-50);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary-dark);
        }

        .req-table th:first-child { border-radius: 8px 0 0 0; }
        .req-table th:last-child { border-radius: 0 8px 0 0; }

        .req-table td {
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .req-table tr:last-child td { border-bottom: none; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.pass {
            background: #ecfdf5;
            color: #059669;
        }

        .status-badge.fail {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text);
            margin-bottom: 0.4rem;
        }

        .form-group label .required {
            color: var(--error);
            margin-left: 2px;
        }

        .form-group .hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.3rem;
        }

        .form-control {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 0.92rem;
            font-family: inherit;
            color: var(--text);
            background: white;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(15,118,110,0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            padding-right: 2.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            font-size: 0.92rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 2px 8px rgba(15,118,110,0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 4px 16px rgba(15,118,110,0.4);
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: white;
            color: var(--text);
            border: 1.5px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--primary-50);
            border-color: var(--primary-light);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 4px 16px rgba(239,68,68,0.4);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }

        .btn-success:hover {
            box-shadow: 0 4px 16px rgba(16,185,129,0.4);
            transform: translateY(-1px);
        }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* Alerts */
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            font-size: 0.88rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            line-height: 1.5;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert-info {
            background: var(--primary-50);
            color: var(--primary-dark);
            border: 1px solid var(--primary-100);
        }

        /* Success page */
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 24px rgba(16,185,129,0.3);
        }

        .success-icon svg {
            width: 44px;
            height: 44px;
            fill: white;
        }

        .success-details {
            background: var(--primary-50);
            border-radius: 8px;
            padding: 1.25rem;
            margin: 1.5rem 0;
        }

        .success-details h4 {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.75rem;
        }

        .success-details ul {
            list-style: none;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .success-details ul li {
            padding: 0.35rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-details ul li::before {
            content: '✓';
            color: var(--success);
            font-weight: 700;
        }

        .security-warning {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 1.25rem;
            margin: 1.5rem 0;
        }

        .security-warning h4 {
            color: #991b1b;
            font-size: 0.92rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .security-warning p {
            color: #991b1b;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        /* Test connection button */
        .btn-test {
            padding: 0.5rem 1rem;
            font-size: 0.82rem;
            background: var(--primary-50);
            color: var(--primary);
            border: 1.5px solid var(--primary-100);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s;
        }

        .btn-test:hover {
            background: var(--primary-100);
        }

        .test-result {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-body { animation: fadeIn 0.4s ease; }

        /* Responsive */
        @media (max-width: 640px) {
            body { padding: 1rem 0.5rem; }
            .card-body { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .stepper { padding: 1rem; }
            .step-label { display: none; }
            .actions { flex-direction: column; gap: 0.75rem; }
            .actions .btn { width: 100%; }
        }

        .version-tag {
            display: inline-block;
            background: var(--primary-50);
            color: var(--primary);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 3px;
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>

<div class="installer-header">
    <div class="logo">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-3 10h-3v3h-2v-3H8v-2h3V8h2v3h3v2z"/></svg>
    </div>
    <h1>MedWell Pharmacy</h1>
    <p class="subtitle">Installation Wizard <span class="version-tag">v<?php echo MW_VERSION; ?></span></p>
</div>

<div class="card">
    <!-- Stepper -->
    <div class="stepper">
        <?php for ($i = 1; $i <= 6; $i++):
            $labels = ['Welcome', 'Database', 'Import', 'Admin', 'Settings', 'Complete'];
            $class = $i < $step ? 'completed' : ($i === $step ? 'active' : '');
        ?>
        <div class="step-item <?php echo $class; ?>">
            <div class="step-circle">
                <?php echo $i < $step ? '✓' : $i; ?>
            </div>
            <span class="step-label"><?php echo $labels[$i-1]; ?></span>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Progress Bar -->
    <div style="padding: 0 2rem;">
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo (($step - 1) / 5) * 100; ?>%"></div>
        </div>
    </div>

    <div class="card-body">

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
        <div class="alert alert-error">
            <span>⚠</span>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Step 1: Welcome & Requirements -->
    <?php if ($step === 1): ?>
        <h2>Welcome to MedWell Pharmacy</h2>
        <p class="desc">
            This wizard will guide you through the installation process. Before we begin, 
            let's verify your server meets the minimum requirements.
        </p>

        <table class="req-table">
            <thead>
                <tr>
                    <th>Requirement</th>
                    <th>Current</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirements as $check): ?>
                <tr>
                    <td><?php echo htmlspecialchars($check['label']); ?></td>
                    <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars($check['value']); ?></td>
                    <td>
                        <span class="status-badge <?php echo $check['status'] ? 'pass' : 'fail'; ?>">
                            <?php echo $check['status'] ? '✓ Pass' : '✗ Fail'; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$allRequirementsMet): ?>
        <div class="alert alert-warning">
            <span>⚠</span>
            <span>Some requirements are not met. Please fix them before continuing.</span>
        </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="step" value="2">
            <div class="actions">
                <span></span>
                <button type="submit" class="btn btn-primary" <?php echo !$allRequirementsMet ? 'disabled' : ''; ?>>
                    Get Started →
                </button>
            </div>
        </form>

    <!-- Step 2: Database Configuration -->
    <?php elseif ($step === 2): ?>
        <h2>Database Configuration</h2>
        <p class="desc">
            Enter your MySQL database connection details. If the database doesn't exist, 
            we'll attempt to create it for you.
        </p>

        <form method="post" id="dbForm">
            <input type="hidden" name="step" value="2">
            
            <div class="form-group">
                <label>Database Host <span class="required">*</span></label>
                <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_config']['db_host'] ?? 'localhost'); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Database Port <span class="required">*</span></label>
                    <input type="text" name="db_port" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_config']['db_port'] ?? '3306'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Database Name <span class="required">*</span></label>
                    <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_config']['db_name'] ?? 'medwell_pharmacy'); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Database User <span class="required">*</span></label>
                    <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_config']['db_user'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" class="form-control" value="<?php echo htmlspecialchars($_SESSION['db_config']['db_pass'] ?? ''); ?>">
                </div>
            </div>

            <div style="margin-top: 0.5rem;">
                <button type="button" class="btn-test" onclick="testConnection()">🔌 Test Connection</button>
                <div id="testResult" class="test-result"></div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="document.forms.dbForm.step.value='1'; document.forms.dbForm.submit();">← Back</button>
                <button type="submit" class="btn btn-primary">Continue →</button>
            </div>
        </form>

    <!-- Step 3: Import SQL -->
    <?php elseif ($step === 3): ?>
        <h2>Database Import</h2>
        <p class="desc">
            The installer will import the database schema from <code>medwell_pharmacy.sql</code>. 
            This file should be located in the root directory of the application.
        </p>

        <div class="alert alert-info">
            <span>ℹ</span>
            <div>
                <strong>Important:</strong> Ensure <code>medwell_pharmacy.sql</code> exists in the application root. 
                If the file is missing, you can upload it via FTP and refresh this page.
            </div>
        </div>

        <?php
        $sql_file = __DIR__ . '/medwell_pharmacy.sql';
        $sql_exists = file_exists($sql_file);
        ?>
        <table class="req-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Path</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>medwell_pharmacy.sql</td>
                    <td style="font-size: 0.82rem; color: var(--text-secondary);">/medwell_pharmacy.sql</td>
                    <td>
                        <span class="status-badge <?php echo $sql_exists ? 'pass' : 'fail'; ?>">
                            <?php echo $sql_exists ? '✓ Found' : '✗ Missing'; ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <form method="post">
            <input type="hidden" name="step" value="3">
            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="this.form.step.value='2'; this.form.submit();">← Back</button>
                <button type="submit" class="btn btn-primary" <?php echo !$sql_exists ? 'disabled' : ''; ?>>
                    ⚡ Import Database
                </button>
            </div>
        </form>

    <!-- Step 4: Admin Account -->
    <?php elseif ($step === 4): ?>
        <h2>Create Admin Account</h2>
        <p class="desc">
            Set up the primary administrator account. This account will have full access 
            to the pharmacy management system.
        </p>

        <form method="post">
            <input type="hidden" name="step" value="4">

            <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" name="admin_fullname" class="form-control" value="<?php echo htmlspecialchars($_SESSION['admin_config']['admin_fullname'] ?? 'Administrator'); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="admin_username" class="form-control" value="<?php echo htmlspecialchars($_SESSION['admin_config']['admin_username'] ?? 'admin'); ?>" required>
                    <p class="hint">Minimum 3 characters</p>
                </div>
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['admin_config']['admin_email'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="admin_password" class="form-control" required id="admin_password">
                    <p class="hint">Minimum 8 characters, at least one uppercase and one number</p>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <input type="password" name="admin_confirm" class="form-control" required>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="this.form.step.value='3'; this.form.submit();">← Back</button>
                <button type="submit" class="btn btn-primary">Continue →</button>
            </div>
        </form>

    <!-- Step 5: Site Configuration -->
    <?php elseif ($step === 5): ?>
        <h2>Site Configuration</h2>
        <p class="desc">
            Configure your pharmacy's basic settings. These can be changed later from the admin panel.
        </p>

        <form method="post">
            <input type="hidden" name="step" value="5">

            <div class="form-group">
                <label>Pharmacy Name <span class="required">*</span></label>
                <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['site_config']['site_name'] ?? 'MedWell Pharmacy'); ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Timezone</label>
                    <select name="site_timezone" class="form-control">
                        <?php
                        $selected_tz = $_SESSION['site_config']['site_timezone'] ?? 'UTC';
                        $popular_tzs = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','Europe/London','Europe/Berlin','Asia/Kolkata','Asia/Tokyo','Australia/Sydney'];
                        foreach ($popular_tzs as $tz): ?>
                            <option value="<?php echo $tz; ?>" <?php echo $tz === $selected_tz ? 'selected' : ''; ?>><?php echo $tz; ?></option>
                        <?php endforeach; ?>
                        <option disabled>──────────</option>
                        <?php foreach ($timezones as $tz): ?>
                            <?php if (!in_array($tz, $popular_tzs)): ?>
                            <option value="<?php echo $tz; ?>" <?php echo $tz === $selected_tz ? 'selected' : ''; ?>><?php echo $tz; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency Code</label>
                    <select name="site_currency" class="form-control">
                        <?php
                        $currencies = [
                            'USD' => 'USD - US Dollar',
                            'EUR' => 'EUR - Euro',
                            'GBP' => 'GBP - British Pound',
                            'INR' => 'INR - Indian Rupee',
                            'PHP' => 'PHP - Philippine Peso',
                            'CAD' => 'CAD - Canadian Dollar',
                            'AUD' => 'AUD - Australian Dollar',
                            'NGN' => 'NGN - Nigerian Naira',
                            'KES' => 'KES - Kenyan Shilling',
                            'ZAR' => 'ZAR - South African Rand'
                        ];
                        $selected_curr = $_SESSION['site_config']['site_currency'] ?? 'USD';
                        foreach ($currencies as $code => $label): ?>
                            <option value="<?php echo $code; ?>" <?php echo $code === $selected_curr ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Currency Symbol</label>
                <input type="text" name="site_currency_symbol" class="form-control" value="<?php echo htmlspecialchars($_SESSION['site_config']['site_currency_symbol'] ?? '$'); ?>" style="max-width: 80px;">
            </div>

            <div class="alert alert-warning">
                <span>⚠</span>
                <div><strong>Ready to install:</strong> Clicking "Install Now" will write configuration files and set up the database. Make sure all details are correct.</div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="this.form.step.value='4'; this.form.submit();">← Back</button>
                <button type="submit" class="btn btn-success">⚡ Install Now</button>
            </div>
        </form>

    <!-- Step 6: Complete -->
    <?php elseif ($step === 6): ?>
        <div style="text-align: center;">
            <div class="success-icon">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
            </div>
            <h2>Installation Complete!</h2>
            <p class="desc">
                MedWell Pharmacy has been successfully installed and configured. 
                Your system is ready to use.
            </p>
        </div>

        <div class="success-details">
            <h4>What was configured:</h4>
            <ul>
                <li>Database connection established</li>
                <li>Database schema imported</li>
                <li>Administrator account created</li>
                <li>Configuration file written</li>
                <li>Security settings applied</li>
            </ul>
        </div>

        <div class="security-warning">
            <h4>🔒 Security Recommendation</h4>
            <p>
                For security reasons, you should delete <code>install.php</code> from your server. 
                This prevents anyone from re-running the installation. You can delete it now using the button below, 
                or manually via FTP.
            </p>
        </div>

        <form method="post">
            <input type="hidden" name="step" value="6">
            <input type="hidden" name="delete_install" value="1">
            <div class="actions" style="justify-content: center; gap: 1rem;">
                <button type="submit" class="btn btn-danger">🗑 Delete install.php</button>
                <a href="index.php" class="btn btn-primary">Launch MedWell Pharmacy →</a>
            </div>
        </form>

    <?php endif; ?>

    </div>
</div>

<p style="margin-top: 1.5rem; color: var(--text-secondary); font-size: 0.78rem; text-align: center;">
    MedWell Pharmacy v<?php echo MW_VERSION; ?> &copy; <?php echo date('Y'); ?> | Installation Wizard
</p>

<script>
function testConnection() {
    const form = document.getElementById('dbForm');
    const data = new FormData(form);
    data.append('action', 'test_connection');
    
    const result = document.getElementById('testResult');
    result.innerHTML = '<span style="color: var(--text-secondary)">Testing connection...</span>';
    
    // Use AJAX to test connection via a simple approach
    // We'll submit to a temporary check using the same page
    fetch(window.location.href, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        // Parse the response to check for errors
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const alerts = doc.querySelectorAll('.alert-error');
        if (alerts.length > 0) {
            let errorMsg = '';
            alerts.forEach(a => errorMsg += a.textContent.trim() + ' ');
            result.innerHTML = '<span style="color: #dc2626">✗ ' + errorMsg.trim() + '</span>';
        } else {
            // Check if we moved to step 3 (success)
            const stepInput = doc.querySelector('input[name="step"][value="3"]');
            if (stepInput || !doc.querySelector('.alert-error')) {
                result.innerHTML = '<span style="color: #059669">✓ Connection successful!</span>';
            } else {
                result.innerHTML = '<span style="color: #dc2626">✗ Connection failed</span>';
            }
        }
    })
    .catch(err => {
        result.innerHTML = '<span style="color: #dc2626">✗ Request failed: ' + err.message + '</span>';
    });
}
</script>

</body>
</html>
