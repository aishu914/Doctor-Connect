<?php
/**
 * Application Global Configuration
 * Doctor Connect Platform
 */

// Error reporting for development (set to 0 in production)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // 2 hours session lifetime
    ini_set('session.gc_maxlifetime', '7200');
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Compute Base URL dynamically for flexibility
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine script path relative to document root
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    
    // Extract base folder
    $dir = dirname($scriptName);
    // Find where the project root sits
    // If inside /api or /patient or /doctor or /admin, strip that last folder
    $subDirs = ['/api', '/patient', '/doctor', '/admin', '/video', '/config', '/database', '/includes'];
    $baseUrlPath = $dir;
    foreach ($subDirs as $sd) {
        if (str_ends_with($baseUrlPath, $sd)) {
            $baseUrlPath = substr($baseUrlPath, 0, -strlen($sd));
            break;
        }
    }
    
    $baseUrlPath = rtrim($baseUrlPath, '/');
    define('BASE_URL', $protocol . $host . $baseUrlPath);
    define('APP_ROOT', dirname(__DIR__));
}

// Application Constants
define('APP_NAME', 'DoctorConnect');
define('APP_TAGLINE', 'Live Token-Based Online Consultation');
define('CURRENCY_SYMBOL', '₹');
define('JITSI_DEFAULT_DOMAIN', 'meet.jit.si');
define('CANCELLATION_CUTOFF_HOURS', 2);
define('AVG_CONSULTATION_MINS_DEFAULT', 15);

// Include database & helpers
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
