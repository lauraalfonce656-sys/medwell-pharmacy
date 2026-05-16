<?php
/**
 * MedWell Pharmacy - Configuration File
 * 
 * Copy this file as config.php and update the values below.
 * NEVER commit your actual config.php with real credentials to version control.
 */

// Database Configuration
define('DB_HOST', 'localhost');          // Your database host (usually localhost)
define('DB_NAME', 'medwell_pharmacy');   // Your database name
define('DB_USER', 'root');              // Your database username
define('DB_PASS', '');                  // Your database password

// Application Settings
define('APP_NAME', 'MedWell Pharmacy');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/medwell-pharmacy');  // Your app URL

// Timezone
define('TIMEZONE', 'Africa/Dar_es_Salaam');

// Currency
define('CURRENCY', 'TZS');
define('CURRENCY_SYMBOL', 'TSh');

// Tax Rate (%)
define('TAX_RATE', 18);

// Session Security
define('SESSION_LIFETIME', 3600);       // 1 hour in seconds
define('SESSION_NAME', 'medwell_session');

// Error Reporting (set to 0 in production)
define('DEBUG_MODE', true);

// File Upload Settings
define('MAX_UPLOAD_SIZE', 2097152);     // 2MB in bytes
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// CSRF Token Expiry (seconds)
define('CSRF_TOKEN_EXPIRY', 3600);      // 1 hour
