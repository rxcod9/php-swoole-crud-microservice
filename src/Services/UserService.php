<?php

namespace App\Services;

use App\Repositories\UserRepository;

final class UserService
{
    public function __construct(private UserRepository $repo)
    {
    }
    public function create(array $data): array
    {
      // TODO: validation, dedupe
        $id = $this->repo->create($data);
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
    public function update(int $id, array $data): ?array
    {
        $this->repo->update($id, $data);
        return $this->repo->find($id);
    }
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
