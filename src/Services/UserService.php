<?php

namespace App\Services;

use App\Repositories\UserRepository;

/**
 * Class UserService
 *
 * Service layer for User entity.
 * Encapsulates business logic and interacts with UserRepository.
 *
 * @package App\Services
 */
final class UserService
{
    /**
     * UserRepository instance for data access operations.
     *
     * @var UserRepository
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
    public function create(array $data): array
    {
        // TODO: Add validation and deduplication logic here.
        $id = $this->repo->create($data);
        return $this->repo->find($id);
    }

    /**
     * Get a user by ID.
     *
     * @param int $id User ID.
     * @return array|null User record or null if not found.
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * List users with pagination.
     *
     * @param int $limit Number of records to return.
     * @param int $offset Number of records to skip.
     * @return array List of user records.
     */
    public function list(int $limit = 100, int $offset = 0): array
    {
        return $this->repo->list($limit, $offset);
    }

    /**
     * Count total users.
     */
    public function count(): int
    {
        return $this->repo->count();
    }

    /**
     * Update a user by ID and return the updated user data.
     *
     * @param int $id User ID.
     * @param array $data Updated user data.
     * @return array|null Updated user record or null if not found.
     */
    public function update(int $id, array $data): ?array
    {
        $this->repo->update($id, $data);
        return $this->repo->find($id);
    }

    /**
     * Delete a user by ID.
     *
     * @param int $id User ID.
     * @return bool True if deletion was successful, false otherwise.
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
