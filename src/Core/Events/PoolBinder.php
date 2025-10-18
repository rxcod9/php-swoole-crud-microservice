<?php

/**
 * src/Core/Events/PoolBinder.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/PoolBinder.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;

/**
 * Class PoolBinder
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class PoolBinder
{
    public function __construct(
        private PDOPool &$pdoPool,
        private RedisPool &$redisPool
    ) {
        // Empty Constructor
    }

    public function bind(Container $container): void
    {
        $container->bind(PDOPool::class, fn (): \App\Core\Pools\PDOPool => $this->pdoPool);
        $container->bind(RedisPool::class, fn (): \App\Core\Pools\RedisPool => $this->redisPool);
    }
}
