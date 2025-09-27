<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Pools\PDOPool;

use function in_array;

use InvalidArgumentException;

use function is_int;

use PDO;
use RuntimeException;

/**
 * Repository for managing items in the database.
 *
 * This class provides CRUD operations for the 'items' table using PDO.
 */
final class ItemRepository
{
    /**
     * Constructor to initialize the repository with a database context.
     *
     * @param PDOPool $pool The database context for managing connections.
     */
    public function __construct(private PDOPool $pool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Create a new item in the database.
     *
     * @param array $d Item data ('sku', 'title', 'price')
     * @return int The ID of the newly created item.
     * @throws RuntimeException If the insert operation fails.
     */
    public function create(array $d): int
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        // Prepare INSERT statement with named parameters
        $stmt = $conn->prepare('INSERT INTO items (sku, title, price) VALUES (:sku, :title, :price)');
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare statement');
        }

        // Bind values safely
        $stmt->bindValue(':sku', $d['sku'], PDO::PARAM_STR);
        $stmt->bindValue(':title', $d['title'], PDO::PARAM_STR);
        $stmt->bindValue(':price', isset($d['price']) ? (float)$d['price'] : null, PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new RuntimeException('Insert failed: ' . implode(' | ', $stmt->errorInfo()));
        }

        return (int)$conn->lastInsertId();
    }

    /**
     * Find an item by ID.
     *
     * @param int $id Item ID
     * @return array|null Item data or null if not found
     */
    public function find(int $id): ?array
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare('SELECT id, sku, title, price, created_at, updated_at FROM items WHERE id=:id LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare statement');
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find an item by SKU.
     *
     * @param string $sku Item SKU
     * @return array|null Item data or null if not found
     */
    public function findBySku(string $sku): ?array
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare('SELECT id, sku, title, price, created_at, updated_at FROM items WHERE sku=:sku LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare statement');
        }

        $stmt->bindValue(':sku', $sku, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List items with optional filters, sorting, and pagination.
     *
     * @param int $limit Max rows
     * @param int $offset Offset
     * @param array $filters Filter conditions
     * @param string $sortBy Sort column
     * @param string $sortDir Sort direction
     * @return array List of items
     */
    public function list(
        int $limit = 100,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $limit = max(1, min($limit, 1000));
        $offset = max(0, $offset);

        $sql = 'SELECT id, sku, title, price, created_at, updated_at FROM items';
        $where = [];
        $params = [];

        // Apply filters
        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }
            switch ($field) {
                case 'sku':
                    $where[] = 'sku = :sku';
                    $params['sku'] = $value;
                    break;
                case 'title':
                    $where[] = 'title LIKE :title';
                    $params['title'] = "%$value%";
                    break;
                case 'created_after':
                    $where[] = 'created_at > :created_after';
                    $params['created_after'] = $value;
                    break;
                case 'created_before':
                    $where[] = 'created_at < :created_before';
                    $params['created_before'] = $value;
                    break;
                default:
                    throw new InvalidArgumentException("Invalid filter {$field}");
            }
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // Sorting
        $allowedSort = ['id', 'sku', 'title', 'price', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $sortBy $sortDir";

        // Pagination
        $sql .= ' LIMIT :offset, :limit';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed');
        }

        // Bind filter parameters
        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(":$key", $val, $type);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count items with optional filters.
     *
     * @param array $filters Filter conditions
     * @return int Number of filtered items
     */
    public function filteredCount(array $filters = []): int
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $sql = 'SELECT count(*) as total FROM items';
        $where = [];
        $params = [];

        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }
            switch ($field) {
                case 'sku':
                    $where[] = 'sku = :sku';
                    $params['sku'] = $value;
                    break;
                case 'title':
                    $where[] = 'title LIKE :title';
                    $params['title'] = "%$value%";
                    break;
                case 'created_after':
                    $where[] = 'created_at > :created_after';
                    $params['created_after'] = $value;
                    break;
                case 'created_before':
                    $where[] = 'created_at < :created_before';
                    $params['created_before'] = $value;
                    break;
                default:
                    throw new InvalidArgumentException("Invalid filter {$field}");
            }
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed');
        }

        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(":$key", $val, $type);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Count all items in the table.
     */
    public function count(): int
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare('SELECT count(*) as total FROM items');
        if (!$stmt) {
            throw new RuntimeException('Prepare failed');
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Update an existing item.
     *
     * @param int $id Item ID
     * @param array $d Item data ('sku', 'title', 'price')
     * @return bool True if updated
     */
    public function update(int $id, array $d): bool
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare('UPDATE items SET sku=:sku, title=:title, price=:price WHERE id=:id');
        if (!$stmt) {
            throw new RuntimeException('Prepare failed');
        }

        $stmt->bindValue(':sku', $d['sku'], PDO::PARAM_STR);
        $stmt->bindValue(':title', $d['title'], PDO::PARAM_STR);
        $stmt->bindValue(':price', (float)$d['price'], PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete an item by ID.
     *
     * @param int $id Item ID
     * @return bool True if deleted
     */
    public function delete(int $id): bool
    {
        /** @var PDO $conn */
        $conn = $this->pool->get();
        defer(fn () => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare('DELETE FROM items WHERE id=:id');
        if (!$stmt) {
            throw new RuntimeException('Prepare failed');
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
