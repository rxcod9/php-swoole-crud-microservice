<?php

/**
 * UserService.php
 * Service layer for User entity.
 * Handles business logic and delegates persistence to UserRepository.
 *
 * @category   Services
 * @package    App\Services
 * @author     Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license    MIT
 * @version    1.0.0
 * @since      2025-10-02
 * @link       https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/UserService.php
 * @subpackage UserService
 */
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use BadMethodCallException;

/**
 * Class UserService
 * Encapsulates business logic for User entity.
 *
 * @category Services
 * @package  App\Services
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @method   int        create(array<string, mixed> $d)
 * @method   array|null find(integer $id)
 * @method   array|null findByEmail(string $email)
 * @method   array      list(integer $limit = 100, integer $offset = 0, array<string, mixed> $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 * @method   int        filteredCount(array<string, mixed> $filters = [])
 * @method   int        count()
 * @method   bool       update(integer $id, array<string, mixed> $d)
 * @method   bool       delete(integer $id)
 */
final class UserService
{
    /**
     * UserService constructor.
     *
     * @param UserRepository $userRepository Injected UserRepository dependency.
     */
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * Create a new user and return the created user data.
     *
     * @param array<string, mixed> $data User data.
     *
     * @return array<string, mixed>|null Created user record or null on failure.
     */
    public function create(array $data): ?array
    {
        $id = $this->userRepository->create($data);
        return $this->userRepository->find($id);
    }

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param int                  $limit   Max rows (default 20, max 1000)
     * @param int                  $offset  Offset for pagination
     * @param array<string, mixed> $filters Associative array of filters
     * @param string               $sortBy  Column to sort by
     * @param string               $sortDir Sort direction ('ASC' or 'DESC')
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string,int>} Records and pagination metadata.
     */
    public function pagination(
        int $limit = 20,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        $total = $this->userRepository->count();
        $pages = (int) ceil($total / $limit);

        if ($total === 0) {
            return [
                [],
                [
                    'count'          => 0,
                    'current_page'   => (int) floor($offset / $limit) + 1,
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
                    'current_page'   => (int) floor($offset / $limit) + 1,
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
            'current_page'   => (int) floor($offset / $limit) + 1,
            'filtered_total' => $filteredTotal,
            'per_page'       => $limit,
            'total_pages'    => $pages,
            'total'          => $total,
        ];

        return [$records, $pagination];
    }

    /**
     * Update a user by ID and return the updated user data.
     *
     * @param int                  $id   User ID.
     * @param array<string, mixed> $data Updated user data.
     *
     * @return array<string, mixed>|null Updated user record or null if not found.
     */
    public function update(int $id, array $data): ?array
    {
        $this->userRepository->update($id, $data);
        return $this->userRepository->find($id);
    }

    /**
     * Magic method to forward calls to the repository.
     *
     * @param string            $name      Name of the method being called.
     * @param array<int, mixed> $arguments Arguments passed to the method.
     *
     * @throws BadMethodCallException If the method does not exist in the repository.
     *
     * @return mixed Result of the repository method call.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!method_exists($this->userRepository, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist in UserRepository', $name));
        }

        return $this->userRepository->{$name}(...$arguments);
    }
}
