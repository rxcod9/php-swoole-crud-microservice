<?php

namespace App\Services\Cache;

use App\Core\Pools\RedisPool;
use Swoole\Coroutine\Redis;

/**
 * RedisCacheService
 *
 * A caching service that uses Redis to cache individual records and
 * versioned lists of records. It provides methods to get, set, and
 * invalidate caches for both records and lists.
 */
final class RedisCacheService
{
    public function __construct(
        private RedisPool $pool,
        private int $recordTtl = 300,
        private int $listTtl = 120
    ) {
        //
    }

    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $key = $this->recordKeyByColumn($entity, $column, $value);
        $value = $redis->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $key = $this->recordKeyByColumn($entity, $column, $value);
        $redis->setex($key, $this->recordTtl, json_encode($data));
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $key = $this->recordKeyByColumn($entity, $column, $value);
        $redis->del($key);
    }

    private function recordKeyByColumn(string $entity, string $column, int|string $value): string
    {
        return "{$entity}:record:{$column}:{$value}";
    }

    public function getRecord(string $entity, int|string $id): mixed
    {
        return $this->getRecordByColumn($entity, 'id', $id);
    }

    public function setRecord(string $entity, int|string $id, mixed $data): void
    {
        $this->setRecordByColumn($entity, 'id', $id, $data);
    }

    public function invalidateRecord(string $entity, int|string $id): void
    {
        $this->invalidateRecordByColumn($entity, 'id', $id);
    }

    /* ---------------------------
     * LIST CACHE (versioned)
     * ---------------------------
     */

    public function getList(string $entity, array $query): mixed
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $version = $this->getListVersion($entity);
        $key = $this->listKey($entity, $query, $version);

        $value = $redis->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setList(string $entity, array $query, mixed $data): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $version = $this->getListVersion($entity);
        $key = $this->listKey($entity, $query, $version);

        $redis->setex($key, $this->listTtl, json_encode($data));
    }

    public function invalidateLists(string $entity): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $redis->incr("{$entity}:list:version");
    }

    private function getListVersion(string $entity): int
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn() => $this->pool->put($redis));

        $versionKey = "{$entity}:list:version";
        $version = $redis->get($versionKey);

        if (!$version) {
            $redis->set($versionKey, 1);
            return 1;
        }

        return (int)$version;
    }

    private function listKey(string $entity, array $query, int $version): string
    {
        ksort($query); // normalize
        $queryString = http_build_query($query);
        return "{$entity}:list:v{$version}:" . md5($queryString);
    }
}
