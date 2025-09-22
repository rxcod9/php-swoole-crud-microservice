<?php

namespace App\Core\Contexts;

use Swoole\Coroutine\Mysql;

/**
 * Class DbContext
 *
 * Wraps a Swoole Coroutine MySQL connection and provides
 * transaction management and query execution utilities.
 *
 * @package App\Core
 */
final class DbContext
{
    /**
     * MySQL connection instance.
     *
     * @var Mysql
     */
    private Mysql $conn;

    /**
     * DbContext constructor.
     *
     * @param Mysql $conn The Swoole Coroutine MySQL connection.
     */
    public function __construct(Mysql $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get the underlying MySQL connection.
     *
     * @return Mysql The Swoole Coroutine MySQL connection.
     */
    public function conn(): Mysql
    {
        return $this->conn;
    }

    /**
     * Begin a database transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function begin(): bool
    {
        return $this->conn->begin();
    }

    /**
     * Commit the current transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function commit(): bool
    {
        return $this->conn->commit();
    }

    /**
     * Rollback the current transaction.
     *
     * @return bool True on success, false on failure.
     */
    public function rollback(): bool
    {
        return $this->conn->rollback();
    }

    /**
     * Execute a SQL query (SELECT, INSERT, UPDATE, DELETE).
     *
     * If parameters are provided, a prepared statement is used.
     *
     * @param string $sql    The SQL query to execute.
     * @param array  $params Optional query parameters for prepared statements.
     *
     * @return array The result set as an array, or an empty array on failure.
     *
     * @throws \RuntimeException If statement preparation fails.
     */
    public function query(string $sql, array $params = []): array
    {
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new \RuntimeException('Prepare failed: ' . $this->conn->error);
            }
            $result = $stmt->execute($params);
        } else {
            $result = $this->conn->query($sql);
        }

        return is_array($result) ? $result : [];
    }
}
