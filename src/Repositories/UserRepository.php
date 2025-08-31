<?php

namespace App\Repositories;

use App\Core\DbContext;
use PDO;

final class UserRepository
{
    public function __construct(private DbContext $ctx)
    {
        //
    }
    public function create(array $d): int
    {
        $conn = $this->ctx->conn();
        $stmt = $conn->prepare("INSERT INTO users (name,email) VALUES (?,?)");
        $stmt->execute([$d['name'], $d['email']]);
        return (int)$conn->lastInsertId();
    }
    public function find(int $id): ?array
    {
        $stmt = $this->ctx->conn()->prepare("SELECT id,name,email,created_at,updated_at FROM users WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    public function list(): array
    {
        return $this->ctx
            ->conn()
            ->query("SELECT id,name,email,created_at,updated_at FROM users ORDER BY id DESC LIMIT 100")
            ->fetchAll(PDO::FETCH_ASSOC);
    }
    public function update(int $id, array $d): bool
    {
        $stmt = $this->ctx->conn()->prepare("UPDATE users SET name=?, email=? WHERE id=?");
        return $stmt->execute([$d['name'], $d['email'], $id]);
    }
    public function delete(int $id): bool
    {
        $stmt = $this->ctx->conn()->prepare("DELETE FROM users WHERE id=?");
        return $stmt->execute([$id]);
    }
}
