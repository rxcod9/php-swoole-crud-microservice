<?php

namespace App\Services;

use App\Repositories\UserRepository;
use BadMethodCallException;

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
     *
     * @param string $name Name of the method being called.
     * @param array<mixed> $arguments Arguments passed to the method.
     *
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
