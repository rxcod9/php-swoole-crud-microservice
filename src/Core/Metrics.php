<?php

/**
 * src/Core/Metrics.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Metrics.php
 */
declare(strict_types=1);

namespace App\Core;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

/**
 * Class Metrics
 * Provides a singleton CollectorRegistry instance for Prometheus metrics collection.
 * Supports both in-memory and Redis adapters for metric storage.
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class Metrics
{
    /**
     * Returns a CollectorRegistry instance using the specified Redis connection.
     *
     * @param mixed $redis The Redis connection instance.
     *
     * @return CollectorRegistry The CollectorRegistry instance.
     */
    public function getCollectorRegistry(\Redis $redis): CollectorRegistry
    {
        return new CollectorRegistry(
            Redis::fromExistingConnection($redis)
        );
    }
}
