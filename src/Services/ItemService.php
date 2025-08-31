<?php

namespace App\Services;

use App\Repositories\ItemRepository;

final class ItemService
{
    public function __construct(private ItemRepository $repo)
    {
    }
    public function create(array $d): array
    {
        $id = $this->repo->create($d);
        return $this->repo->find($id);
    }
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }
    public function list(): array
    {
        return $this->repo->list();
    }
    public function update(int $id, array $d): ?array
    {
        $this->repo->update($id, $d);
        return $this->repo->find($id);
    }
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
