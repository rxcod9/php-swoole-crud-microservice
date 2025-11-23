<?php

/**
 * src/Core/Servers/PoolInitializer.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/PoolInitializer.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Container;
use App\Core\Events\PoolBinder;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;
use App\Services\Cache\CacheService;
use Swoole\Coroutine;

/**
 * Class PoolInitializer
 * Handles all pool initializer operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class PoolInitializer
{
    /**
     * @param array<string, mixed> $config Config
     */
    public function init(Container $container, array $config): void
    {
        $dbConf  = $config['db'][$config['db']['driver'] ?? 'mysql'];
        $pdoPool = new PDOPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);
        Coroutine\run(fn () => $pdoPool->init(-1));

        $redisConf = $config['redis'];
        $redisPool = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);
        Coroutine\run(fn () => $redisPool->init(-1));

        $poolBinder = new PoolBinder($pdoPool, $redisPool);
        $poolBinder->bind($container);

        $cacheService = $container->get(CacheService::class);
        $container->bind(CacheService::class, fn (): mixed => $cacheService);
    }
}
