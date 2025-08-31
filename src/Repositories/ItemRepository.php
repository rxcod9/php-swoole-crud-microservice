<?php

namespace App\Repositories;

use App\Core\DbContext;
use PDO;

final class ItemRepository
{
    public function __construct(private DbContext $ctx) {}
    public function create(array $d): int
    {
        $stmt = $this->ctx->conn()->prepare("INSERT INTO items (sku,title,price) VALUES (?,?,?)");
        $stmt->execute([$d['sku'], $d['title'], $d['price']]);
        return (int)$this->ctx->conn()->lastInsertId();
    }
    public function find(int $id): ?array
    {
        $stmt = $this->ctx->conn()->prepare("SELECT * FROM items WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function list(): array
    {
        return $this->ctx->conn()->query("SELECT * FROM items ORDER BY id DESC limit 100")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function update(int $id, array $d): bool
    {
        $stmt = $this->ctx->conn()->prepare("UPDATE items SET sku=?, title=?, price=? WHERE id=?");
        return $stmt->execute([$d['sku'], $d['title'], $d['price'], $id]);
    }
    public function delete(int $id): bool
    {
        $stmt = $this->ctx->conn()->prepare("DELETE FROM items WHERE id=?");
        return $stmt->execute([$id]);
    }
}
