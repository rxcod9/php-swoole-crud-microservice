<?php

namespace App\Core;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

final class Metrics
{
    private static ?CollectorRegistry $r = null;
    public static function reg(): CollectorRegistry
    {
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
