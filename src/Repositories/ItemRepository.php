<?php

namespace App\Repositories;

use App\Core\DbContext;

final class ItemRepository
{
    public function __construct(private DbContext $ctx) {}

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

    public function list(): array
    {
        $conn = $this->ctx->conn();

        $rows = $conn->query("SELECT id, sku, title, price, created_at, updated_at FROM items ORDER BY id DESC LIMIT 100");
        if ($rows === false) {
            throw new \RuntimeException("Query failed: " . $conn->error);
        }

        return $rows;
    }

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

        return (bool)($result['affected_rows'] ?? 0);
    }

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

        return (bool)($result['affected_rows'] ?? 0);
    }
}
