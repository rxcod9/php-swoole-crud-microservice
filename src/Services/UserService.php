<?php

/**
 * UserService.php
 * Service layer for User entity.
 * Handles business logic and delegates persistence to UserRepository.
 * src/Services/UserService.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/UserService.php
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Pools\PDOPool;
use App\Repositories\UserRepository;
use BadMethodCallException;

/**
 * Class UserService
 * Service layer for User entity.
 * Encapsulates business logic and interacts with UserRepository.
 *
 * @category  Services
 * @package   App\Services
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @method    int   count()
 * @method    array delete(int $id)
 * @method    int   filteredCount()
 * @method    array find(int $id)
 * @method    array findBySku(string $sku)
 * @method    array list(int $limit = 20, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 */
final readonly class UserService
{
    /**
     * UserService constructor.
     *
     * @param UserRepository $userRepository Injected repository for user operations.
     */
    public function __construct(
        private PDOPool $pdoPool,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Create a new user and return the created user data.
     *
     * @param array<int, mixed> $data User data.
     *
     * @return array Created user record.
     */
    public function create(array $data): ?array
    {
        return $this->pdoPool->withConnection(function () use ($data): ?array {
            $id = $this->userRepository->create($data);
            return $this->userRepository->find($id);
        });
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
        return $this->pdoPool->withConnection(function () use (
            $limit,
            $offset,
            $filters,
            $sortBy,
            $sortDir
        ): array {
            // Get total count for pagination metadata
            $total = $this->userRepository->count();
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

            $filteredTotal = $this->userRepository->filteredCount($filters);
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

            $records = $this->userRepository->list(
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
        });
    }

    /**
     * Update an user by ID and return the updated user data.
     *
     * @param int               $id   User ID.
     * @param array<int, mixed> $data Updated user data.
     *
     * @return array|null Updated user record or null if not found.
     */
    public function update(int $id, array $data): ?array
    {
        return $this->pdoPool->withConnection(function () use ($id, $data): ?array {
            $this->userRepository->update($id, $data);
            return $this->userRepository->find($id);
        });
    }

    /**
     * Magic method to forward calls to the repository.
     */
    public function __call(mixed $name, mixed $arguments)
    {
        if (!method_exists($this->userRepository, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist in UserRepository', $name));
        }

        return $this->pdoPool->withConnection(function () use ($name, $arguments): mixed {
            return $this->userRepository->$name(...$arguments);
        });
    }
}
