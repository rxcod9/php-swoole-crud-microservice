<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ItemRepository;
use BadMethodCallException;

use function count;

/**
 * Class ItemService
 *
 * Service layer for Item entity.
 * Encapsulates business logic and interacts with ItemRepository.
 *
 * @method int count()
 * @method array delete(int $id)
 * @method int filteredCount()
 * @method array find(int $id)
 * @method array findBySku(string $sku)
 * @method array list(int $limit = 20, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 *
 * @package App\Services
 */
final class ItemService
{
    /**
     * The repository instance for item data access.
     *
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
     * @param array $data Item data.
     * @return array Created item record.
     */
    public function create(array $data): ?array
    {
        $id = $this->repo->create($data);
        return $this->repo->find($id);
    }

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param int $limit Max rows (default 100, max 1000)
     * @param int $offset Offset for pagination
     * @param array $filters Associative array of filters
     * @param string $sortBy Column to sort by
     * @param string $sortDir Sort direction ('ASC' or 'DESC')
     *
     * @return array Array of records
     */
    public function pagination(
        int $limit = 20,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        // Get total count for pagination metadata
        $total = $this->repo->count();
        $pages = ceil($total / $limit);

        if ($total === 0) {
            return [
                [],
                [
                    'count'          => 0,
                    'current_page'   => floor($offset / $limit) + 1,
                    'filtered_total' => 0,
                    'per_page'       => $limit,
                    'total_pages'    => $pages,
                    'total'          => $total,
                ],
            ];
        }

        $filteredTotal = $this->repo->filteredCount($filters);
        if ($filteredTotal === 0) {
            return [
                [],
                [
                    'count'          => 0,
                    'current_page'   => floor($offset / $limit) + 1,
                    'filtered_total' => 0,
                    'per_page'       => $limit,
                    'total_pages'    => $pages,
                    'total'          => $total,
                ],
            ];
        }

        $records = $this->repo->list(
            limit: $limit,
            offset: $offset,
            filters: $filters,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );

        $pagination = [
            'count'          => count($records),
            'current_page'   => floor($offset / $limit) + 1,
            'filtered_total' => $filteredTotal,
            'per_page'       => $limit,
            'total_pages'    => $pages,
            'total'          => $total,
        ];

        return [$records, $pagination];
    }

    /**
     * Update an item by ID and return the updated item data.
     *
     * @param int $id Item ID.
     * @param array $data Updated item data.
     * @return array|null Updated item record or null if not found.
     */
    public function update(int $id, array $data): ?array
    {
        $this->repo->update($id, $data);
        return $this->repo->find($id);
    }

    /**
     * Magic method to forward calls to the repository.
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->repo, $name)) {
            throw new BadMethodCallException("Method {$name} does not exist in ItemRepository");
        }

        return $this->repo->$name(...$arguments);
    }
}
