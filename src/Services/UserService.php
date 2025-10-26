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
use App\Core\Pools\RetryContext;
use App\Models\User;
use App\Repositories\Repository;
use App\Repositories\UserRepository;
use App\Traits\Retryable;
use BadMethodCallException;
use PDO;

/**
 * Class UserService
 * Service layer for User entity.
 * Encapsulates business logic and interacts with UserRepository.
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
 * @method         User find(int $id)
 * @method         User findByEmail(string $email)
 * @method         array<string, User> list(int $limit = 20, int $offset = 0, array<string, mixed> $filters = [], string $sortBy = 'id', string $sortDir = 'DESC')
 * @template-using User PaginationTrait
 */
final readonly class UserService
{
    use Retryable;

    /**
     * @use PaginationTrait<User>
     */
    use PaginationTrait;

    /**
     * Tag for logging.
     */
    public const TAG = 'UserService';

    /**
     * UserService constructor.
     *
     * @param UserRepository $userRepository Injected repository for user operations.
     */
    public function __construct(
        private PDOPool $pdoPool,
        private UserRepository $userRepository
    ) {
        // Empty Constructor
    }

    /**
     * Get the repository instance.
     *
     * @return UserRepository The repository instance.
     */
    protected function getRepository(): UserRepository
    {
        return $this->userRepository;
    }

    /**
     * Create a new user and return the created user data.
     *
     * @param array<string, mixed> $data User data.
     *
     * @return User Created user record.
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function create(array $data): User
    {
        // return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($data): User {
        $id = $this->userRepository->create($data);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Created user with ID: ' . var_export($id, true));
        $retryContext = new RetryContext();
        return $this->pdoPool->forceRetryConnection($retryContext, function () use ($id): User {
            return $this->userRepository->find($id);
        });
        // });
    }

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param array<string, mixed> $params PaginateParams
     *
     * @return array{0: array<int, User>, 1: array<string, mixed>} Records + metadata
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function pagination(array $params): array
    {
        // return $this->pdoPool->withConnection(function () use (
        //     $params
        // ): array {
        $paginationParams = PaginationParams::fromArray($params);
        [$records, $meta] = $this->paginate($paginationParams);
        // Ensure all records are User instances
        /** @var array<int, User> $records */
        return [$records, $meta];
        // });
    }

    /**
     * Update an user by ID and return the updated user data.
     *
     * @param int               $id   User ID.
     * @param array<string, mixed> $data Updated user data.
     *
     * @return User Updated user record if not found.
     */
    public function update(int $id, array $data): User
    {
        return $this->pdoPool->withConnection(function () use ($id, $data): User {
            $this->userRepository->update($id, $data);
            return $this->userRepository->find($id);
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
        if (!method_exists($this->userRepository, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist in UserRepository', $name));
        }

        return $this->pdoPool->withConnection(function () use ($name, $arguments): mixed {
            return call_user_func_array([$this->userRepository, $name], $arguments);
        });
    }
}
