<?php

namespace App\Repositories;

use App\Core\DbContext;

/**
 * Repository for managing users in the database.
 */
final class UserRepository
{
    /**
     * Constructor to initialize the repository with a database context.
     *
     * @param DbContext $ctx The database context for managing connections.
     */
    public function __construct(private DbContext $ctx)
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
        $conn = $this->ctx->conn(); // returns Swoole\Coroutine\MySQL

        $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['name'], $d['email']]);
        if ($result === false) {
            throw new \RuntimeException("Insert failed: " . $stmt->error);
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
        $conn = $this->ctx->conn();

        $stmt = $conn->prepare("SELECT id, name, email, created_at, updated_at FROM users WHERE id=? LIMIT 1");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $rows = $stmt->execute([$id]);
        if ($rows === false) {
            throw new \RuntimeException("Query failed: " . $stmt->error);
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
        $conn = $this->ctx->conn();

        // Enforce sane ranges
        $limit  = max(1, min($limit, 1000));  // clamp 1–1000
        $offset = max(0, $offset);

        // Use placeholders and execute with params
        $stmt = $conn->prepare("
            SELECT id, name, email, created_at, updated_at
            FROM users
            ORDER BY id DESC
            LIMIT ?, ?
        ");

        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: " . $conn->error);
        }

        // In Swoole, execute() takes params as an array
        $result = $stmt->execute([$offset, $limit]);
        if ($result === false) {
            throw new \RuntimeException("Execute failed: " . $conn->error);
        }

        return $result; // already array of rows
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
        $conn = $this->ctx->conn();

        $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['name'], $d['email'], $id]);
        if ($result === false) {
            throw new \RuntimeException("Update failed: " . $stmt->error);
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
        $conn = $this->ctx->conn();

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$id]);
        if ($result === false) {
            throw new \RuntimeException("Delete failed: " . $stmt->error);
        }

        return (bool)($stmt->affected_rows ?? 0);
    }
}
