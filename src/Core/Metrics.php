<?php

namespace App\Core;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

/**
 * Class Metrics
 *
 * Provides a singleton CollectorRegistry instance for Prometheus metrics collection.
 * Supports both in-memory and Redis adapters for metric storage.
 *
 * @package App\Core
 */
final class Metrics
{
    /**
     * Singleton instance of CollectorRegistry.
     *
     * @var CollectorRegistry|null
     */
    private static ?CollectorRegistry $r = null;

    /**
     * Returns a singleton CollectorRegistry instance.
     *
     * Uses Redis adapter for shared metrics storage between workers.
     * To use in-memory storage (not shared), uncomment the InMemory adapter.
     *
     * @return CollectorRegistry
     */
    public static function reg(): CollectorRegistry
    {
        // Uncomment below to use in-memory adapter (not shared between workers)
        // $adapter = new InMemory();

        // Redis adapter → shared between workers
        $adapter = new Redis([
            'host' => 'redis',
            'port' => 6379,
            'timeout' => 0.1,
            'read_timeout' => 10,
        ]);

        if (!self::$r) {
            self::$r = new CollectorRegistry($adapter);
        }
        return self::$r;
    }
}
