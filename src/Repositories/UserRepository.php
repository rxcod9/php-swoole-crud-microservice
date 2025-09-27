<?php

namespace App\Repositories;

use App\Core\Pools\PDOPool;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class UserRepository
 *
 * Repository for managing users in the database.
 * Provides CRUD operations: create, read, update, delete, and list users.
 *
 * @method int          create(array $d)
 * @method array|null   find(int $id)
 * @method array|null   findByEmail(string $email)
 * @method array        list(int $limit = 100, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 * @method array        filteredCount(array $filters = [])
 * @method int          count()
 * @method bool         update(int $id, array $d)
 * @method bool         delete(int $id)
 *
 * @package App\Repositories
 */
final class UserRepository
{
    /**
     * Constructor
     *
     * @param PDOPool $pool The PDO connection pool
     */
    public function __construct(private PDOPool $pool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Create a new user in the database.
     *
     * @param array $data User data ('name', 'email')
     *
     * @return int Last inserted user ID
     * @throws RuntimeException on failure
     */
    public function create(array $data): int
    {
        /** @var \PDO $conn Get PDO connection from pool */
        $conn = $this->pool->get();
        // Ensure connection is returned to pool when done
        defer(fn() => isset($conn) && $this->pool->put($conn));

        // Prepare INSERT statement with named parameters
        $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (:name, :email)");
        if ($stmt === false) {
            throw new RuntimeException("Failed to prepare statement");
        }

        // Bind values safely to prevent SQL injection
        $stmt->bindValue(':name', $data['name'], \PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], \PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new RuntimeException("Insert failed: " . implode(' | ', $stmt->errorInfo()));
        }

        // Return ID of the newly created user
        return (int)$conn->lastInsertId();
    }

    /**
     * Find a user by ID.
     *
     * @param int $id User ID
     *
     * @return array|null User data or null if not found
     */
    public function find(int $id): ?array
    {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        // Prepare SELECT query
        $stmt = $conn->prepare("SELECT id, name, email, created_at, updated_at FROM users WHERE id=:id LIMIT 1");
        if ($stmt === false) {
            throw new RuntimeException("Failed to prepare statement");
        }

        // Bind ID parameter
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find a user by email.
     *
     * @param string $email Email address
     *
     * @return array|null User data or null
     */
    public function findByEmail(string $email): ?array
    {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare("SELECT id, name, email, created_at, updated_at FROM users WHERE email=:email LIMIT 1");
        if ($stmt === false) {
            throw new RuntimeException("Failed to prepare statement");
        }

        $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List users with optional filters, sorting, and pagination.
     *
     * @param int       $limit Max rows (default 100, max 1000)
     * @param int       $offset Offset for pagination
     * @param array     $filters Associative array of filters
     * @param string    $sortBy Column to sort by
     * @param string    $sortDir Sort direction ('ASC' or 'DESC')
     *
     * @return array    Array of users
     */
    public function list(
        int $limit = 100,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        // Validate pagination values
        $limit  = max(1, min($limit, 1000));
        $offset = max(0, $offset);

        $sql = "SELECT id, name, email, created_at, updated_at FROM users";

        $where = [];
        $params = [];

        // Build dynamic WHERE clause using filters
        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            switch ($field) {
                case 'email':
                    $where[] = "email = :email";
                    $params['email'] = $value;
                    break;
                case 'name':
                    $where[] = "name LIKE :name";
                    $params['name'] = "%$value%";
                    break;
                case 'created_after':
                    $where[] = "created_at > :created_after";
                    $params['created_after'] = $value;
                    break;
                case 'created_before':
                    $where[] = "created_at < :created_before";
                    $params['created_before'] = $value;
                    break;
                default:
                    throw new InvalidArgumentException("Invalid filter {$field}");
            }
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // Validate sorting column
        $allowedSort = ['id', 'name', 'email', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $sortBy $sortDir";

        // Add pagination parameters
        $sql .= " LIMIT :offset, :limit";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException("Prepare failed");
        }

        // Bind filter parameters
        foreach ($params as $key => $val) {
            $type = is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue(":$key", $val, $type);
        }

        // Bind limit and offset
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count filtered users.
     *
     * @param array $filters Optional filters
     * @return int Number of filtered users
     */
    public function filteredCount(array $filters = []): int
    {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        $sql = "SELECT count(*) as total FROM users";

        // Build dynamic WHERE clause using filters
        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            switch ($field) {
                case 'email':
                    $where[] = "email = :email";
                    $params['email'] = $value;
                    break;
                case 'name':
                    $where[] = "name LIKE :name";
                    $params['name'] = "%$value%";
                    break;
                case 'created_after':
                    $where[] = "created_at > :created_after";
                    $params['created_after'] = $value;
                    break;
                case 'created_before':
                    $where[] = "created_at < :created_before";
                    $params['created_before'] = $value;
                    break;
                default:
                    throw new InvalidArgumentException("Invalid filter {$field}");
            }
        }

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException("Prepare failed");
        }

        // Bind filter parameters
        foreach ($params as $key => $val) {
            $type = is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue(":$key", $val, $type);
        }

        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Count total users in the table.
     */
    public function count(): int
    {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare("SELECT count(*) as total FROM users");
        if ($stmt === false) {
            throw new RuntimeException("Prepare failed");
        }

        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Update a user.
     *
     * @param int $id User ID
     * @param array $d User data ('name', 'email')
     *
     * @return bool True if updated
     */
    public function update(int $id, array $d): bool
    {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare("UPDATE users SET name=:name, email=:email WHERE id=:id");
        if ($stmt === false) {
            throw new RuntimeException("Failed to prepare statement");
        }

        $stmt->bindValue(':name', $d['name'], \PDO::PARAM_STR);
        $stmt->bindValue(':email', $d['email'], \PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a user by ID.
     *
     * @param int $id User ID
     *
     * @return bool True if deleted
     */
    public function delete(int $id): bool
    {
        /** @var \PDO $conn */
        $conn = $this->pool->get();
        defer(fn() => $conn && $this->pool->put($conn));

        $stmt = $conn->prepare("DELETE FROM users WHERE id=:id");
        if ($stmt === false) {
            throw new RuntimeException("Failed to prepare statement");
        }

        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
