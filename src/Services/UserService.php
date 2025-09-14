<?php

namespace App\Services;

use App\Repositories\UserRepository;

/**
 * Service layer for User entity.
 * Encapsulates business logic and interacts with UserRepository.
 */
final class UserService
{
    /**
     * Inject UserRepository for data access operations.
     */
    public function __construct(private UserRepository $repo)
    {
        //
    }

    /**
     * Create a new user and return the created user data.
     * @param array $data User data
     * @return array Created user record
     */
    public function create(array $data): array
    {
        // TODO: validation, dedupe
        $id = $this->repo->create($data);
        return $this->repo->find($id);
    }

    /**
     * Get a user by ID.
     * @param int $id User ID
     * @return array|null User record or null if not found
     */
    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * List users with pagination.
     * @param int $limit Number of records to return
     * @param int $offset Number of records to skip
     * @return array List of user records
     */
    public function list(int $limit = 100, int $offset = 0): array
    {
        return $this->repo->list($limit, $offset);
    }

    /**
     * Update a user by ID and return the updated user data.
     * @param int $id User ID
     * @param array $data Updated user data
     * @return array|null Updated user record or null if not found
     */
    public function update(int $id, array $data): ?array
    {
        $this->repo->update($id, $data);
        return $this->repo->find($id);
    }

    /**
     * Delete a user by ID.
     * @param int $id User ID
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
