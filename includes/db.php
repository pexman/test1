<?php
require_once __DIR__ . '/../config/config.php';

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $host = DB_HOST;
        $dbname = DB_NAME;
        $charset = DB_CHARSET ?? 'utf8mb4';
        $user = DB_USER;
        $pass = DB_PASS;

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection error');
        }

        if (defined('APP_TIMEZONE')) {
            $this->pdo->exec("SET time_zone='" . addslashes(APP_TIMEZONE) . "'");
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
