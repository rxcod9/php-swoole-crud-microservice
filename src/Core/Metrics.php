<?php

/**
 * src/Core/Metrics.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Metrics.php
 */
declare(strict_types=1);

namespace App\Core;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;

/**
 * Class Metrics
 * Provides a singleton CollectorRegistry instance for Prometheus metrics collection.
 * Supports both in-memory and Redis adapters for metric storage.
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class Metrics
{
    /**
     * Singleton instance of CollectorRegistry.
     */
    private static ?CollectorRegistry $collectorRegistry = null;

    /**
     * Returns a singleton CollectorRegistry instance.
     *
     * Uses Redis adapter for shared metrics storage between workers.
     * To use in-memory storage (not shared), uncomment the InMemory adapter.
     */
    public static function reg(): CollectorRegistry
    {
        // Uncomment below to use in-memory adapter (not shared between workers)
        $inMemory = new InMemory();

        // Redis adapter → shared between workers
        // $adapter = new Redis([
        //     'host' => 'redis',
        //     'port' => 6379,
        //     'timeout' => 0.1,
        //     'read_timeout' => 10,
        // ]);

        if (!self::$collectorRegistry instanceof \Prometheus\CollectorRegistry) {
            self::$collectorRegistry = new CollectorRegistry($inMemory);
        }

        return self::$collectorRegistry;
    }
}
