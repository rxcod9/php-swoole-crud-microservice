<?php

namespace App\Repositories;

use App\Core\DbContext;

/**
 * Repository for managing items in the database.
 */
final class ItemRepository
{
    /**
     * Constructor to initialize the repository with a database context.
     *
     * @param DbContext $ctx The database context for managing connections.
     */
    public function __construct(private DbContext $ctx) {}

    /**
     * Create a new item in the database.
     *
     * @param array $d The data for the new item (expects 'sku', 'title', 'price').
     * @return int The ID of the newly created item.
     * @throws \RuntimeException If the insert operation fails.
     */
    public function create(array $d): int
    {
        $conn = $this->ctx->conn(); // returns Swoole\Coroutine\MySQL

        $stmt = $conn->prepare("INSERT INTO items (sku, title, price) VALUES (?, ?, ?)");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['sku'], $d['title'], (float)$d['price']]);
        if ($result === false) {
            throw new \RuntimeException("Insert failed: " . $stmt->error);
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
        $conn = $this->ctx->conn();

        $stmt = $conn->prepare("SELECT id, sku, title, price, created_at, updated_at FROM items WHERE id=? LIMIT 1");
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
     * List items with a default limit.
     *
     * @return array An array of items, each represented as an associative array.
     * @throws \RuntimeException If the query operation fails.
     */
    public function list(): array
    {
        $conn = $this->ctx->conn();

        $rows = $conn->query("SELECT id, sku, title, price, created_at, updated_at FROM items ORDER BY id DESC LIMIT 100");
        if ($rows === false) {
            throw new \RuntimeException("Query failed: " . $conn->error);
        }

        return $rows;
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
        $conn = $this->ctx->conn();

        $stmt = $conn->prepare("UPDATE items SET sku=?, title=?, price=? WHERE id=?");
        if ($stmt === false) {
            throw new \RuntimeException("Failed to prepare statement: " . $conn->error);
        }

        $result = $stmt->execute([$d['sku'], $d['title'], (float)$d['price'], $id]);
        if ($result === false) {
            throw new \RuntimeException("Update failed: " . $stmt->error);
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
        $conn = $this->ctx->conn();

        $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
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
