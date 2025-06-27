<?php

namespace App\Lib;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private ?PDO $pdo = null;

    public function __construct()
    {
        // Ensure config.php is loaded. This might be better handled by a central bootstrap file.
        if (!defined('DB_HOST')) {
            // Attempt to load from project root if not already loaded
            $configFile = __DIR__ . '/../../config.php';
            if (file_exists($configFile)) {
                require_once $configFile;
            } else {
                // Log error or throw exception if config is essential and missing
                error_log("Database Error: Configuration file not found or constants (DB_HOST, etc.) not defined.");
                // Optionally, throw an exception to halt execution if DB is critical
                // throw new \Exception("Database configuration is missing.");
                return; // Or handle error appropriately
            }
        }

        // Check if constants are defined after attempting to load config
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            error_log("Database Error: DB connection constants (DB_HOST, DB_NAME, DB_USER, DB_PASS) are not defined.");
            return;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error, but don't expose details to the public
            error_log('Database Connection Error: ' . $e->getMessage());
            // Optionally, throw a generic exception or handle as per application requirements
            // throw new PDOException("Could not connect to the database.");
            $this->pdo = null; // Ensure PDO is null if connection fails
        }
    }

    /**
     * Executes a SQL query with parameters and returns the statement object.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params An array of parameters to bind to the query.
     * @return PDOStatement|false The PDOStatement object on success, or false on failure.
     */
    public function query(string $sql, array $params = []): PDOStatement|false
    {
        if ($this->pdo === null) {
            error_log("Database query failed: No active PDO connection.");
            return false;
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database Query Error: ' . $e->getMessage() . ' SQL: ' . $sql . ' Params: ' . print_r($params, true));
            return false;
        }
    }

    /**
     * Get the last inserted ID.
     *
     * @return string|false The last inserted ID, or false on failure.
     */
    public function lastInsertId(): string|false
    {
        if ($this->pdo === null) {
            error_log("Database lastInsertId failed: No active PDO connection.");
            return false;
        }
        return $this->pdo->lastInsertId();
    }

    /**
     * Check if the PDO connection is established.
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }
}
?>
