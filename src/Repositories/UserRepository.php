<?php

namespace App\Repositories;

use App\Core\DbContext;

final class UserRepository
{
    public function __construct(private DbContext $ctx)
    {
        //
    }

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

    public function list(): array
    {
        $conn = $this->ctx->conn();

        $rows = $conn->query("SELECT id, name, email, created_at, updated_at FROM users ORDER BY id DESC LIMIT 100");
        if ($rows === false) {
            throw new \RuntimeException("Query failed: " . $conn->error);
        }

        return $rows;
    }

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

        return (bool)($result['affected_rows'] ?? 0);
    }

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

        return (bool)($result['affected_rows'] ?? 0);
    }
}
