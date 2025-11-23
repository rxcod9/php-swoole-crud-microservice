<?php

/**
 * src/Tasks/UpdateUserTask.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/UpdateUserTask.php
 */
declare(strict_types=1);

namespace App\Tasks\User;

use App\Services\Cache\CacheService;
use App\Services\UserService;
use App\Tasks\Task;

/**
 * UpdateUserTask handles update user operation.
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class UpdateUserTask extends Task
{
    public const TAG = 'UpdateUserTask';

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
     * Update a user by ID.
     * Expects JSON body with updated user data.
     *
     * @param array<string, string|null> $params Route parameters
     * @param array<string, mixed> $data
     *
     * @return bool Whether Updated
     */
    public function update(array $params, array $data): bool
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'params+data ' . json_encode([
            'params' => $params,
            'data'   => $data,
        ], JSON_PRETTY_PRINT));

        $id = (int)$params['id'];

        $updated = $this->userService->getRepository()->update((int)$params['id'], $data);

        $this->cacheService->invalidateRecord('users', $id);
        $this->cacheService->invalidateRecordByColumn('users', 'email', (string) $data['email']);
        $this->cacheService->invalidateLists('users');

        return $updated;
    }
}
