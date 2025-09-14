<?php

namespace App\Services;

use App\Repositories\ItemRepository;

/**
 * Service layer for Item entity.
 * Encapsulates business logic and interacts with ItemRepository.
 */
final class ItemService
{
    /**
     * Inject ItemRepository for data access operations.
     */
    public function __construct(private ItemRepository $repo)
    {
        //
    }

    /**
     * Create a new item and return the created item data.
     * @param array $d Item data
     * @return array Created item record
     */
    public function create(array $d): array
    {
        $id = $this->repo->create($d);
        return $this->repo->find($id);
    }

    /**
     * Get an item by ID.
     * @param int $id Item ID
     * @return array|null Item record or null if not found
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * List all items.
     * @return array List of item records
     */
    public function list(): array
    {
        return $this->repo->list();
    }

    /**
     * Update an item by ID and return the updated item data.
     * @param int $id Item ID
     * @param array $d Updated item data
     * @return array|null Updated item record or null if not found
     */
    public function update(int $id, array $d): ?array
    {
        $this->repo->update($id, $d);
        return $this->repo->find($id);
    }

    /**
     * Delete an item by ID.
     * @param int $id Item ID
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
