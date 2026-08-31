<?php
/**
 * Database Configuration & Connection
 * Flesh and Blood TCG Sandbox
 * Secure PDO Singleton
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'fab_tcg';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::DB_HOST,
                self::DB_PORT,
                self::DB_NAME,
                self::DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
            } catch (PDOException $e) {
                // If database does not exist, connect without dbname to allow setup
                if ($e->getCode() === 1049) {
                    $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=%s', self::DB_HOST, self::DB_PORT, self::DB_CHARSET);
                    self::$instance = new PDO($dsnNoDb, self::DB_USER, self::DB_PASS, $options);
                } else {
                    error_log('Database Connection Error: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database connection failure. Check MySQL in XAMPP.']);
                    exit;
                }
            }
        }

        return self::$instance;
    }

    public static function getRawConnection(): PDO {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', self::DB_HOST, self::DB_PORT, self::DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];
        return new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
    }
}
