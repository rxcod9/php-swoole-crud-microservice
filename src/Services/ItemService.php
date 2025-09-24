<?php

namespace App\Services;

use App\Repositories\ItemRepository;

/**
 * Class ItemService
 *
 * Service layer for Item entity.
 * Encapsulates business logic and interacts with ItemRepository.
 *
 * @method int      count()
 * @method array    delete(int $id)
 * @method int      filteredCount()
 * @method array    find(int $id)
 * @method array    findBySku(string $sku)
 * @method array    list(int $limit = 100, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 *
 * @package App\Services
 */
final class ItemService
{
    /**
     * The repository instance for item data access.
     *
     * @var ItemRepository
     */
    private ItemRepository $repo;

    /**
     * ItemService constructor.
     *
     * @param ItemRepository $repo Injected repository for item operations.
     */
    public function __construct(ItemRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Create a new item and return the created item data.
     *
     * @param array $d Item data.
     * @return array Created item record.
     */
    public function create(array $d): array
    {
        $id = $this->repo->create($d);
        return $this->repo->find($id);
    }

    /**
     * Update an item by ID and return the updated item data.
     *
     * @param int $id Item ID.
     * @param array $d Updated item data.
     * @return array|null Updated item record or null if not found.
     */
    public function update(int $id, array $d): ?array
    {
        $this->repo->update($id, $d);
        return $this->repo->find($id);
    }

    /**
     * Magic method to forward calls to the repository.
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->repo, $name)) {
            throw new \BadMethodCallException("Method {$name} does not exist in ItemRepository");
        }

        return $this->repo->$name(...$arguments);
    }
}
