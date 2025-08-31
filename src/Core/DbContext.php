<?php

namespace App\Core;

use Swoole\Coroutine\Mysql;

final class DbContext
{
    private Mysql $conn;

    public function __construct(Mysql $conn)
    {
        $this->conn = $conn;
    }

    public function conn(): Mysql
    {
        return $this->conn;
    }

    /**
     * Begin a transaction
     */
    public function begin(): bool
    {
        return $this->conn->begin();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->conn->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->conn->rollback();
    }

    /**
     * Execute a query (SELECT / INSERT / UPDATE / DELETE)
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
