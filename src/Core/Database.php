<?php
/**
 * Database Connection Manager
 * Handles MySQL database connections using PDO
 */

namespace P2P\Core;

use PDO;
use PDOException;
use P2P\Core\Config;

class Database
{
    private static ?PDO $connection = null;
    private static ?Database $instance = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        if (self::$connection !== null) {
            return;
        }

        $config = Config::all();
        $dbConfig = $config['database'] ?? [];

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? 3306;
        $database = $dbConfig['database'] ?? 'p2p_network';
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';
        $charset = $dbConfig['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }
    }

    /**
     * Get PDO connection
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::getInstance();
        }
        return self::$connection;
    }

    /**
     * Execute a query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::$connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return self::$connection->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): void
    {
        self::$connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): void
    {
        self::$connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): void
    {
        self::$connection->rollBack();
    }

    /**
     * Get row count
     */
    public function rowCount(\PDOStatement $stmt): int
    {
        return $stmt->rowCount();
    }

    /**
     * Close connection
     */
    public static function close(): void
    {
        self::$connection = null;
        self::$instance = null;
    }
}
