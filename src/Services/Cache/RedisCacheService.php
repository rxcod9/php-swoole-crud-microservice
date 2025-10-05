<?php

/**
 * src/Services/Cache/RedisCacheService.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Services
 * @package   App\Services\Cache
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/Cache/RedisCacheService.php
 */
declare(strict_types=1);

namespace App\Services\Cache;

use App\Core\Pools\RedisPool;

/**
 * RedisCacheService
 * A caching service that uses Redis to cache individual records and
 * versioned lists of records. It provides methods to get, set, and
 * invalidate caches for both records and lists.
 *
 * @category  Services
 * @package   App\Services\Cache
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class RedisCacheService
{
    public const string TAG = 'REDIS';

    public function __construct(
        private RedisPool $redisPool,
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
    public function get(string $key): mixed
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        return $redis->get($key);
    }

    public function set(string $key, mixed $data, ?int $localTtl = null): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $redis->setex($key, $localTtl ?? $this->recordTtl, $data);
    }

    public function incrBy(string $key, int $increment, ?int $localTtl = null): int
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $count = $redis->incrBy($key, $increment);
        if ($count === $increment) {
            $redis->expire($key, $localTtl ?? $this->recordTtl);
        }

        return $count;
    }

    public function incr(string $key, ?int $localTtl = null): int
    {
        return $this->incrBy($key, 1, $localTtl);
    }

    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $key  = $this->recordKeyByColumn($entity, $column, $value);
        $data = $this->get($key);

        return $data ? json_decode($data, true) : null;
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data, ?int $localTtl = null): void
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        $this->set($key, json_encode($data), $localTtl ?? $this->recordTtl);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $key = $this->recordKeyByColumn($entity, $column, $value);
        $redis->del($key);
    }

    private function recordKeyByColumn(string $entity, string $column, int|string $value): string
    {
        return sprintf('%s:record:%s:%s', $entity, $column, $value);
    }

    public function getRecord(string $entity, int|string $id): mixed
    {
        return $this->getRecordByColumn($entity, 'id', $id);
    }

    public function setRecord(string $entity, int|string $id, mixed $data, ?int $localTtl = null): void
    {
        $this->setRecordByColumn($entity, 'id', $id, $data, $localTtl);
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
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        $value = $redis->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setList(string $entity, array $query, mixed $data, ?int $localTtl = null): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        $redis->setex($key, $localTtl ?? $this->listTtl, json_encode($data));
    }

    public function invalidateLists(string $entity): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $redis->incr($entity . ':version');
    }

    private function getListVersion(string $entity): int
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $versionKey = $entity . ':version';
        $version    = $redis->get($versionKey);

        if (!$version) {
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

        return sprintf('%s:list:v%d:%s', $entity, $version, $hash);
    }

    /**
     * Garbage collect old list versions for multiple entities in one scan.
     *
     * @param string[] $entities
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        $redis = $this->redisPool->get();
        defer(fn () => $this->redisPool->put($redis));

        $versions = $this->getCurrentVersions($entities);

        $cursor = 0;
        do {
            $keys = $redis->scan($cursor, '*:list:v*', 100);
            if (!is_array($keys)) {
                // ensure cursor continues correctly
                continue;
            }

            if ($keys === []) {
                // ensure cursor continues correctly
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
     *
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
            if (str_starts_with($key, $entity . ':list:v')) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Extract the version number from a Redis list key.
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
     */
    public function gc(): void
    {
        $this->gcOldListVersions(['users', 'items']);
    }
}
