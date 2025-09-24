<?php

namespace App\Repositories;

use App\Core\Pools\MySQLPool;

/**
 * Repository for managing items in the database.
 *
 * This class provides CRUD operations for the 'items' table using a Swoole\Coroutine\MySQL connection.
 */
final class ItemRepository
{
    /**
     * Constructor to initialize the repository with a database context.
     *
     * @param MySQLPool $pool The database context for managing connections.
     */
    public function __construct(private MySQLPool $pool)
    {
        //
    }

    /**
     * Create a new item in the database.
     *
     * @param array $d The data for the new item (expects 'sku', 'title', 'price').
     * @return int The ID of the newly created item.
     * @throws \RuntimeException If the insert operation fails.
     */
    public function create(array $d): int
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get(); // returns Swoole\Coroutine\MySQL
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("INSERT INTO items (sku, title, price) VALUES (?, ?, ?)");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([
            $d['sku'],
            $d['title'],
            isset($d['price']) ? (float)$d['price'] : null,
        ]);
        if ($result === false) {
            throw new \RuntimeException("Insert failed: " . $conn->error);
        }

        return (int)$conn->insert_id;
    }

    /**
     * Find an item by its ID.
     *
     * @param int $id The ID of the item to find.
     * @return array|null The item data as an associative array, or null if not found.
     * @throws \RuntimeException If the query operation fails.
     */
    public function find(int $id): ?array
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("SELECT id, sku, title, price, created_at, updated_at FROM items WHERE id=? LIMIT 1");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $rows = $stmt->execute([$id]);
        if ($rows === false) {
            throw new \RuntimeException("Query failed: " . $conn->error);
        }

        return $rows[0] ?? null;
    }

    /**
     * Find an item by its SKU.
     *
     * @param int $sku The SKU of the item to find.
     * @return array|null The item data as an associative array, or null if not found.
     * @throws \RuntimeException If the query operation fails.
     */
    public function findBySku(string $sku): ?array
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("SELECT id, sku, title, price, created_at, updated_at FROM items WHERE sku=? LIMIT 1");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $rows = $stmt->execute([$sku]);
        if ($rows === false) {
            throw new \RuntimeException("Query failed: " . $conn->error);
        }

        return $rows[0] ?? null;
    }

    /**
     * List items with a default limit.
     *
     * @return array An array of items, each represented as an associative array.
     * @throws \RuntimeException If the query operation fails.
     */
    public function list(
        int $limit = 100,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        /** @var \Swoole\Coroutine\Mysql $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $limit  = max(1, min($limit, 1000));
        $offset = max(0, $offset);

        $sql = "SELECT id, sku, title, price, created_at, updated_at FROM items";
        $where = [];
        $params = [];

        // filters
        foreach ($filters as $field => $value) {
            if(is_null($value)) {
                continue;
            }
            switch ($field) {
                case 'sku':
                    $where[] = "sku = ?";
                    $params[] = $value;
                    break;
                case 'title':
                    $where[] = "title LIKE ?";
                    $params[] = "%$value%";
                    break;
                case 'created_after':
                    $where[] = "created_at > ?";
                    $params[] = $value;
                    break;
                case 'created_before':
                    $where[] = "created_at < ?";
                    $params[] = $value;
                    break;
            }
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // order by (only allow known columns)
        $allowedSort = ['id', 'sku', 'title', 'price', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $sortBy $sortDir";

        // pagination
        $sql .= " LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: " . $conn->error);
        }

        $result = $stmt->execute($params);
        if ($result === false) {
            throw new \RuntimeException("Execute failed: " . $conn->error);
        }

        return $result;
    }

    /**
     * Filtered count of items.
     *
     * @return array An array of items, each represented as an associative array.
     * @throws \RuntimeException If the query operation fails.
     */
    public function filteredCount(
        array $filters = []
    ): int {
        /** @var \Swoole\Coroutine\Mysql $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $sql = "SELECT count(*) as total FROM items";
        $where = [];
        $params = [];

        // filters
        foreach ($filters as $field => $value) {
            if(is_null($value)) {
                continue;
            }
            switch ($field) {
                case 'sku':
                    $where[] = "sku = ?";
                    $params[] = $value;
                    break;
                case 'title':
                    $where[] = "title LIKE ?";
                    $params[] = "%$value%";
                    break;
                case 'created_after':
                    $where[] = "created_at > ?";
                    $params[] = $value;
                    break;
                case 'created_before':
                    $where[] = "created_at < ?";
                    $params[] = $value;
                    break;
            }
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: " . $conn->error);
        }

        $result = $stmt->execute($params);
        if ($result === false) {
            throw new \RuntimeException("Execute failed: " . $conn->error);
        }

        return $result[0]['total'] ?? 0;
    }

    /**
     * Count items.
     */
    public function count(): int
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("
            SELECT count(*) as total
            FROM items
        ");

        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: " . $conn->error);
        }

        $result = $stmt->execute();
        if ($result === false) {
            throw new \RuntimeException("Execute failed: " . $conn->error);
        }

        return $result[0]['total'] ?? 0;
    }

    /**
     * Update an existing item.
     *
     * @param int $id The ID of the item to update.
     * @param array $d The data to update (expects 'sku', 'title', 'price').
     * @return bool True if the item was updated, false otherwise.
     * @throws \RuntimeException If the update operation fails.
     */
    public function update(int $id, array $d): bool
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("UPDATE items SET sku=?, title=?, price=? WHERE id=?");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['sku'], $d['title'], (float)$d['price'], $id]);
        if ($result === false) {
            throw new \RuntimeException("Update failed: " . $conn->error);
        }

        return (bool)($stmt->affected_rows ?? 0);
    }

    /**
     * Delete an item by its ID.
     *
     * @param int $id The ID of the item to delete.
     * @return bool True if the item was deleted, false otherwise.
     * @throws \RuntimeException If the delete operation fails.
     */
    public function delete(int $id): bool
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$id]);
        if ($result === false) {
            throw new \RuntimeException("Delete failed: " . $conn->error);
        }

        return (bool)($stmt->affected_rows ?? 0);
    }
}
