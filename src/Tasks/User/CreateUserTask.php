<?php

/**
 * src/Tasks/CreateUserTask.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/CreateUserTask.php
 */
declare(strict_types=1);

namespace App\Tasks\User;

use App\Services\Cache\CacheService;
use App\Services\UserService;
use App\Tasks\Task;

/**
 * CreateUserTask handles create user operation.
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class CreateUserTask extends Task
{
    public const TAG = 'CreateUserTask';

    /**
     * Inject UserService for business logic operations.
     */
    public function __construct(
        private readonly UserService $userService,
        private readonly CacheService $cacheService
    ) {
        // Empty Constructor
    }

    /**
     * Create a new user.
     * Expects JSON body with user data.
     *
     * @param array<string, mixed> $data
     * @return int Created user id
     */
    public function create(array $data): int
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'data ' . json_encode($data, JSON_PRETTY_PRINT));
        $userId = $this->userService->getRepository()->create($data);

        // Invalidate cache
        $this->cacheService->invalidateLists('users');

        return $userId;
    }
}
