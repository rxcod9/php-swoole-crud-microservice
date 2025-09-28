<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Core\Pools\RedisPool;

use function is_array;

use Redis;

/**
 * RedisCacheService
 *
 * A caching service that uses Redis to cache individual records and
 * versioned lists of records. It provides methods to get, set, and
 * invalidate caches for both records and lists.
 */
final class RedisCacheService
{
    public const TAG = 'REDIS';

    public function __construct(
        private RedisPool $pool,
        private int $recordTtl = 300,
        private int $listTtl = 120
    ) {
        //
    }

    /**
     * ---------------------------
     * RECORD CACHE
     * ---------------------------
     */
    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->pool->put($redis));

        $key   = $this->recordKeyByColumn($entity, $column, $value);
        $value = $redis->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->pool->put($redis));

        $key = $this->recordKeyByColumn($entity, $column, $value);
        $redis->setex($key, $this->recordTtl, json_encode($data));
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->pool->put($redis));

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
        defer(fn () => $this->pool->put($redis));

        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        $value = $redis->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setList(string $entity, array $query, mixed $data): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->pool->put($redis));

        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        $redis->setex($key, $this->listTtl, json_encode($data));
    }

    public function invalidateLists(string $entity): void
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->pool->put($redis));

        $redis->incr("{$entity}:version");
    }

    private function getListVersion(string $entity): int
    {
        $redis = $this->pool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->pool->put($redis));

        $versionKey = "{$entity}:version";
        $version    = $redis->get($versionKey);

        if (!$version) {
            $redis->set($versionKey, 1);
            return 1;
        }

        return (int)$version;
    }

    private function listKey(string $entity, array $query, int $version): string
    {
        ksort($query); // normalize params
        $queryString = http_build_query($query);

        // SHA-256 is secure, collision-resistant, and fast enough
        $hash = hash('sha256', $queryString);

        return "{$entity}:list:v{$version}:{$hash}";
    }

    /**
     * Garbage collect old list versions for multiple entities in one scan.
     *
     * @param string[] $entities
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        $redis = $this->pool->get();
        defer(fn () => $this->pool->put($redis));

        $versions = $this->getCurrentVersions($entities);

        $cursor = 0;
        do {
            $keys = $redis->scan($cursor, '*:list:v*', 100);
            if (!is_array($keys) || empty($keys)) {
                $cursor = $cursor ?: 0; // ensure cursor continues correctly
                continue;
            }

            foreach ($keys as $key) {
                $entity = $this->findEntityForKey($key, $entities);
                if ($entity === null) {
                    continue;
                }

                $version = $this->extractVersionFromKey($key);
                if ($version <= ($versions[$entity] - $keepVersions)) {
                    $redis->del($key);
                }
            }
        } while ($cursor !== 0);
    }

    /**
     * Get current list version for each entity.
     *
     * @param string[] $entities
     * @return array<string,int>
     */
    private function getCurrentVersions(array $entities): array
    {
        $versions = [];
        foreach ($entities as $entity) {
            $versions[$entity] = $this->getListVersion($entity);
        }
        return $versions;
    }

    /**
     * Determine which entity a Redis key belongs to.
     *
     * @param string[] $entities
     */
    private function findEntityForKey(string $key, array $entities): ?string
    {
        foreach ($entities as $entity) {
            if (str_starts_with($key, "{$entity}:list:v")) {
                return $entity;
            }
        }
        return null;
    }

    /**
     * Extract the version number from a Redis list key.
     *
     */
    private function extractVersionFromKey(string $key): int
    {
        if (preg_match('/v(\d+):/', $key, $matches) && isset($matches[1])) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * Garbage collect old list versions for an entity.
     *
     */
    public function gc(): void
    {
        $this->gcOldListVersions(['users', 'items']);
    }
}
