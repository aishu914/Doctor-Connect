<?php
/**
 * Database Connection Configuration using PDO
 * Doctor Connect Platform (Supports XAMPP & WAMP out-of-the-box)
 */

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('DB_NAME') ?: 'doctor_connect');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_CHARSET', 'utf8mb4');
}

class Database {
    private static ?PDO $instance = null;
    private static ?string $activePort = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            // WAMP/XAMPP port fallback list: default configured port, 3306, 3307 (WAMP MariaDB), 3308
            $portsToTry = array_unique([DB_PORT, '3306', '3307', '3308']);
            $lastException = null;

            foreach ($portsToTry as $port) {
                try {
                    $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                    self::$activePort = (string)$port;
                    return self::$instance;
                } catch (PDOException $e) {
                    $lastException = $e;
                    // If error is database doesn't exist (1049), port is correct, but DB not seeded yet
                    if (str_contains($e->getMessage(), 'Unknown database') || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1049)) {
                        throw $e;
                    }
                    // Otherwise try next port in case WAMP is using 3307 or 3308 for MariaDB/MySQL
                    continue;
                }
            }

            error_log("Database Connection Error: " . ($lastException ? $lastException->getMessage() : 'Unknown'));
            throw new PDOException("Database connection failed. Please ensure MySQL/MariaDB is running in WampServer/XAMPP and database '" . DB_NAME . "' exists. Error: " . ($lastException ? $lastException->getMessage() : ''));
        }

        return self::$instance;
    }

    public static function getActivePort(): ?string {
        return self::$activePort;
    }
}
