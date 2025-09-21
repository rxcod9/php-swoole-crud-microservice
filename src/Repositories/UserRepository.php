<?php

namespace App\Repositories;

use App\Core\MySQLPool;

/**
 * Class UserRepository
 *
 * Repository for managing users in the database.
 * Provides CRUD operations: create, read, update, delete, and list users.
 *
 * @package App\Repositories
 */
final class UserRepository
{
    /**
     * UserRepository constructor.
     *
     * @param MySQLPool $pool The database context for managing connections.
     */
    public function __construct(private MySQLPool $pool)
    {
        //
    }

    /**
     * Create a new user in the database.
     *
     * @param array $d The data for the new user (expects 'name', 'email').
     * @return int The ID of the newly created user.
     * @throws \RuntimeException If the insert operation fails.
     */
    public function create(array $d): int
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => isset($conn) && $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['name'], $d['email']]);
        if ($result === false) {
            throw new \RuntimeException("Insert failed: " . $conn->error);
        }

        return (int)$conn->insert_id;
    }

    /**
     * Find a user by its ID.
     *
     * @param int $id The ID of the user to find.
     * @return array|null The user data as an associative array, or null if not found.
     * @throws \RuntimeException If the query operation fails.
     */
    public function find(int $id): ?array
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("SELECT id, name, email, created_at, updated_at FROM users WHERE id=? LIMIT 1");
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
     * List users with pagination.
     *
     * @param int $limit The maximum number of users to return (default 100, max 1000).
     * @param int $offset The offset from which to start returning users (default 0).
     * @return array An array of users, each represented as an associative array.
     * @throws \RuntimeException If the query operation fails.
     */
    public function list(int $limit = 100, int $offset = 0): array
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $limit  = max(1, min($limit, 1000));
        $offset = max(0, $offset);

        $stmt = $conn->prepare("
            SELECT id, name, email, created_at, updated_at
            FROM users
            ORDER BY id DESC
            LIMIT ?, ?
        ");

        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: " . $conn->error);
        }

        $result = $stmt->execute([$offset, $limit]);
        if ($result === false) {
            throw new \RuntimeException("Execute failed: " . $conn->error);
        }

        return $result;
    }

    /**
     * Count users with pagination.
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
            FROM users
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
     * Update an existing user.
     *
     * @param int $id The ID of the user to update.
     * @param array $d The data to update (expects 'name', 'email').
     * @return bool True if the user was updated, false otherwise.
     * @throws \RuntimeException If the update operation fails.
     */
    public function update(int $id, array $d): bool
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['name'], $d['email'], $id]);
        if ($result === false) {
            throw new \RuntimeException("Update failed: " . $conn->error);
        }

        return (bool)($stmt->affected_rows ?? 0);
    }

    /**
     * Delete a user by its ID.
     *
     * @param int $id The ID of the user to delete.
     * @return bool True if the user was deleted, false otherwise.
     * @throws \RuntimeException If the delete operation fails.
     */
    public function delete(int $id): bool
    {
        /**
         * @var \Swoole\Coroutine\Mysql $conn
         */
        $conn = $this->pool->get();
        defer(fn() => $conn->connected && $this->pool->put($conn));

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
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
