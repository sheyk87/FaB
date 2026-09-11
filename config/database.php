<?php
/**
 * Database Configuration & Connection
 * Flesh and Blood TCG Sandbox
 * Secure PDO Singleton
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    // Configuración por defecto (modificable para servidores externos como InfinityFree/cPanel)
    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'fab_tcg';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    public static function getDbHost(): string {
        return getenv('DB_HOST') ?: (defined('DB_HOST') ? (string)constant('DB_HOST') : self::DB_HOST);
    }

    public static function getDbPort(): string {
        return getenv('DB_PORT') ?: (defined('DB_PORT') ? (string)constant('DB_PORT') : self::DB_PORT);
    }

    public static function getDbName(): string {
        return getenv('DB_NAME') ?: (defined('DB_NAME') ? (string)constant('DB_NAME') : self::DB_NAME);
    }

    public static function getDbUser(): string {
        return getenv('DB_USER') ?: (defined('DB_USER') ? (string)constant('DB_USER') : self::DB_USER);
    }

    public static function getDbPass(): string {
        $env = getenv('DB_PASS');
        if ($env !== false) return $env;
        if (defined('DB_PASS')) return (string)constant('DB_PASS');
        return self::DB_PASS;
    }

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::getDbHost(),
                self::getDbPort(),
                self::getDbName(),
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, self::getDbUser(), self::getDbPass(), $options);
            } catch (PDOException $e) {
                // If database does not exist, try connect without dbname to allow initial setup
                if ($e->getCode() === 1049) {
                    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=%s', self::getDbHost(), self::getDbPort(), self::DB_CHARSET);
                    self::$instance = new PDO($dsnNoDb, self::getDbUser(), self::getDbPass(), $options);
                } else {
                    error_log('Database Connection Error: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database connection failure: ' . $e->getMessage()]);
                    exit;
                }
            }
        }

        return self::$instance;
    }

    public static function getRawConnection(): PDO {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', self::getDbHost(), self::getDbPort(), self::DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];
        return new PDO($dsn, self::getDbUser(), self::getDbPass(), $options);
    }
}
