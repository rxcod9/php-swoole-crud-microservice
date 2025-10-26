<?php

/**
 * ItemService.php
 * Service layer for Item entity.
 * Handles business logic and delegates persistence to ItemRepository.
 * src/Services/ItemService.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Services
 * @package   App\Services
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/ItemService.php
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Pools\PDOPool;
use App\Core\Pools\RetryContext;
use App\Models\Item;
use App\Repositories\ItemRepository;
use App\Repositories\Repository;
use App\Traits\Retryable;
use BadMethodCallException;
use PDO;

/**
 * Class ItemService
 * Service layer for Item entity.
 * Encapsulates business logic and interacts with ItemRepository.
 *
 * @category       Services
 * @package        App\Services
 * @author         Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright      Copyright (c) 2025
 * @license        MIT
 * @version        1.0.0
 * @since          2025-10-02
 * @method         int   count()
 * @method         bool delete(int $id)
 * @method         int   filteredCount()
 * @method         Item find(int $id)
 * @method         Item findBySku(string $sku)
 * @method         array<string, Item> list(int $limit = 20, int $offset = 0, array<string, mixed> $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 * @template-using Item PaginationTrait
 */
final readonly class ItemService
{
    use Retryable;

    /**
     * @use PaginationTrait<Item>
     */
    use PaginationTrait;

    public const TAG = 'ItemService';

    /**
     * ItemService constructor.
     *
     * @param ItemRepository $itemRepository Injected repository for item operations.
     */
    public function __construct(
        private PDOPool $pdoPool,
        private ItemRepository $itemRepository
    ) {
        // Empty Constructor
    }

    /**
     * Get the repository instance.
     *
     * @return ItemRepository The repository instance.
     */
    protected function getRepository(): ItemRepository
    {
        return $this->itemRepository;
    }

    /**
     * Create a new item and return the created item data.
     *
     * @param array<string, mixed> $data Item data.
     *
     * @return Item Created item record.
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function create(array $data): Item
    {
        // return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($data): array {
        $id = $this->itemRepository->create($data);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Created item with ID: ' . var_export($id, true));
        $retryContext = new RetryContext();
        return $this->pdoPool->forceRetryConnection($retryContext, function () use ($id): Item {
            return $this->itemRepository->find($id);
        });
        // });
    }

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param array<string, mixed> $params PaginateParams
     *
     * @return array{0: array<int, Item>, 1: array<string, mixed>} Records + metadata
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function pagination(array $params): array
    {
        // return $this->pdoPool->withConnection(function () use (
        //     $params
        // ): array {
        $paginationParams = PaginationParams::fromArray($params);
        [$records, $meta] = $this->paginate($paginationParams);
        // Ensure all records are Item instances
        /** @var array<int, Item> $records */
        return [$records, $meta];
        // });
    }

    /**
     * Update an item by ID and return the updated item data.
     *
     * @param int               $id   Item ID.
     * @param array<string, mixed> $data Updated item data.
     *
     * @return Item Updated item record.
     */
    public function update(int $id, array $data): Item
    {
        return $this->pdoPool->withConnection(function () use ($id, $data): Item {
            $this->itemRepository->update($id, $data);
            return $this->itemRepository->find($id);
        });
    }

    /**
     * Magic method to forward calls to the repository.
     *
     * @param mixed $name Method name.
     * @param mixed $arguments Method arguments.
     * @return mixed Result from the repository method.
     * @throws BadMethodCallException If the method does not exist in the repository.
     */
    public function __call(mixed $name, mixed $arguments): mixed
    {
        if (!method_exists($this->itemRepository, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist in ItemRepository', $name));
        }

        return $this->pdoPool->withConnection(function () use ($name, $arguments): mixed {
            return call_user_func_array([$this->itemRepository, $name], $arguments);
        });
    }
}
