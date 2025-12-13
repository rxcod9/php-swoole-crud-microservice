<?php

/**
 * src/Tasks/DeleteUserTask.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/DeleteUserTask.php
 */
declare(strict_types=1);

namespace App\Tasks\User;

use App\Services\Cache\CacheService;
use App\Services\UserService;
use App\Tasks\Task;

/**
 * DeleteUserTask handles delete user operation.
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class DeleteUserTask extends Task
{
    public const TAG = 'DeleteUserTask';

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
     * Delete a user by ID.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Deletion status
     */
    public function destroy(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'params ' . json_encode($params, JSON_PRETTY_PRINT));
        $id = (int)$params['id'];
        $ok = $this->userService->delete($id);
        if ($ok) {
            $this->cacheService->invalidateRecord('users', $id);
            $this->cacheService->invalidateLists('users');
        }

        return ['deleted' => $ok];
    }
}
