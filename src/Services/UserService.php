<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use BadMethodCallException;

use function count;

/**
 * Class UserService
 *
 * Service layer for User entity.
 * Encapsulates business logic and interacts with UserRepository.
 *
 * @method int create(array $d)
 * @method array|null find(int $id)
 * @method array|null findByEmail(string $email)
 * @method array list(int $limit = 100, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 * @method array filteredCount(array $filters = [])
 * @method int count()
 * @method bool update(int $id, array $d)
 * @method bool delete(int $id)
 *
 * @package App\Services
 */
final class UserService
{
    /**
     * UserRepository instance for data access operations.
     *
     */
    private UserRepository $repo;

    /**
     * UserService constructor.
     *
     * @param UserRepository $repo Injected UserRepository dependency.
     */
    public function __construct(UserRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Create a new user and return the created user data.
     *
     * @param array $data User data.
     * @return array Created user record.
     */
    public function create(array $data): ?array
    {
        // TODO: Add validation and deduplication logic here.
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
     * Update a user by ID and return the updated user data.
     *
     * @param int $id User ID.
     * @param array<string,mixed> $data Updated user data.
     * @return array<string,mixed>|null Updated user record or null if not found.
     */
    public function update(int $id, array $data): ?array
    {
        $this->repo->update($id, $data);
        return $this->repo->find($id);
    }

    /**
     * Magic method to forward calls to the repository.
     *
     * @param string $name Name of the method being called.
     * @param array<mixed> $arguments Arguments passed to the method.
     * @return mixed Result of the repository method call.
     * @throws BadMethodCallException If the method does not exist in the repository.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!method_exists($this->repo, $name)) {
            throw new BadMethodCallException("Method {$name} does not exist in UserRepository");
        }

        return $this->repo->{$name}(...$arguments);
    }
}
