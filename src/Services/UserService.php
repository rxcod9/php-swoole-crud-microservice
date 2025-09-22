<?php

namespace App\Services;

use App\Repositories\UserRepository;

/**
 * Class UserService
 *
 * Service layer for User entity.
 * Encapsulates business logic and interacts with UserRepository.
 *
 * @method array list(int $limit = 100, int $offset = 0, array $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 * @method array find(int $id)
 * @method array findByEmail(string $email)
 * @method array delete(int $id)
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
     * Magic method to forward calls to the repository.
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->repo, $name)) {
            throw new \BadMethodCallException("Method {$name} does not exist in UserRepository");
        }

        return $this->repo->$name(...$arguments);
    }
}
