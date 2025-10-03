<?php

/**
 * src/Services/ItemService.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Services
 * @package  App\Services
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/ItemService.php
 */
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ItemRepository;
use BadMethodCallException;

/**
 * Class ItemService
 * Service layer for Item entity.
 * Encapsulates business logic and interacts with ItemRepository.
 *
 * @category Services
 * @package  App\Services
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @method   int   count()
 * @method   array delete(int $id)
 * @method   int   filteredCount()
 * @method   array find(int $id)
 * @method   array findBySku(string $sku)
 * @method   array list(int $limit = 20, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 */
final readonly class ItemService
{
    /**
     * ItemService constructor.
     *
     * @param ItemRepository $itemRepository Injected repository for item operations.
     */
    public function __construct(private ItemRepository $itemRepository)
    {
    }

    /**
     * Create a new item and return the created item data.
     *
     * @param array<int, mixed> $data Item data.
     *
     * @return array Created item record.
     */
    public function create(array $data): ?array
    {
        $id = $this->itemRepository->create($data);
        return $this->itemRepository->find($id);
    }

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param int               $limit   Max rows (default 100, max 1000)
     * @param int               $offset  Offset for pagination
     * @param array<int, mixed> $filters Associative array of filters
     * @param string            $sortBy  Column to sort by
     * @param string            $sortDir Sort direction ('ASC' or 'DESC')
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
        $total = $this->itemRepository->count();
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

        $filteredTotal = $this->itemRepository->filteredCount($filters);
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

        $records = $this->itemRepository->list(
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
     * @param int               $id   Item ID.
     * @param array<int, mixed> $data Updated item data.
     *
     * @return array|null Updated item record or null if not found.
     */
    public function update(int $id, array $data): ?array
    {
        $this->itemRepository->update($id, $data);
        return $this->itemRepository->find($id);
    }

    /**
     * Magic method to forward calls to the repository.
     */
    public function __call(mixed $name, mixed $arguments)
    {
        if (!method_exists($this->itemRepository, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist in ItemRepository', $name));
        }

        return $this->itemRepository->$name(...$arguments);
    }
}
