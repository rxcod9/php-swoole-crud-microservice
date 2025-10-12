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
    /**
     * Cache type identifier
     */
    public const CACHE_TYPE = 'REDIS';

    /**
     * Service tag for logging and identification
     */
    public const string TAG = 'RedisCacheService';

    /**
     * Constructor
     *
     * @param RedisPool $redisPool  The Redis connection pool
     * @param int       $recordTtl  Time-to-live for individual records (in seconds)
     * @param int       $listTtl    Time-to-live for lists of records (in seconds)
     */
    public function __construct(
        private RedisPool $redisPool,
        private int $recordTtl = 300,
        private int $listTtl = 120
    ) {
        //
    }

    /**
     * Get a value from the cache by key.
     *
     * @param string $key The cache key
     * @return mixed The cached value or null if not found
     */
    public function get(string $key): mixed
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        return $redis->get($key);
    }

    /**
     * Set a value in the cache with an optional TTL.
     *
     * @param string   $key      The cache key
     * @param mixed    $data     The value to cache
     * @param int|null $localTtl Optional TTL for this specific key (in seconds)
     */
    public function set(string $key, mixed $data, ?int $localTtl = null): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $redis->setex($key, $localTtl ?? $this->recordTtl, $data);
    }

    /**
     * Increment a numeric value in the cache by a specified amount.
     * If the key does not exist, it is created with the increment value and TTL.
     *
     * @param string   $key        The cache key
     * @param int      $increment  The amount to increment by
     * @param int|null $localTtl   Optional TTL for this specific key (in seconds)
     * @return int The new value after incrementing
     */
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

    /**
     * Increment a numeric value in the cache by 1.
     * If the key does not exist, it is created with the value 1 and TTL.
     *
     * @param string   $key      The cache key
     * @param int|null $localTtl Optional TTL for this specific key (in seconds)
     * @return int The new value after incrementing
     */
    public function incr(string $key, ?int $localTtl = null): int
    {
        return $this->incrBy($key, 1, $localTtl);
    }

    /**
     * Get an individual record from the cache by entity, column, and value.
     *
     * @param string     $entity The entity name (e.g., 'users', 'items')
     * @param string     $column The column name (e.g., 'id', 'email')
     * @param int|string $value  The value to look up
     * @return mixed The cached record or null if not found
     */
    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $key  = $this->recordKeyByColumn($entity, $column, $value);
        $data = $this->get($key);

        return $data === null ? null : json_decode($data, true);
    }

    /**
     * Set an individual record in the cache by entity, column, and value.
     *
     * @param string     $entity   The entity name (e.g., 'users', 'items')
     * @param string     $column   The column name (e.g., 'id', 'email')
     * @param int|string $value    The value to look up
     * @param mixed      $data     The record data to cache
     * @param int|null   $localTtl Optional TTL for this specific record (in seconds)
     */
    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data, ?int $localTtl = null): void
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        $this->set($key, json_encode($data), $localTtl ?? $this->recordTtl);
    }

    /**
     * Invalidate an individual record in the cache by entity, column, and value.
     *
     * @param string     $entity The entity name (e.g., 'users', 'items')
     * @param string     $column The column name (e.g., 'id', 'email')
     * @param int|string $value  The value to look up
     */
    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $key = $this->recordKeyByColumn($entity, $column, $value);
        $redis->del($key);
    }

    /**
     * Generate a Redis key for an individual record based on entity, column, and value.
     *
     * @param string     $entity The entity name (e.g., 'users', 'items')
     * @param string     $column The column name (e.g., 'id', 'email')
     * @param int|string $value  The value to look up
     * @return string The generated Redis key
     */
    private function recordKeyByColumn(string $entity, string $column, int|string $value): string
    {
        return sprintf('%s:record:%s:%s', $entity, $column, $value);
    }

    /**
     * Get an individual record from the cache by entity and ID.
     *
     * @param string     $entity The entity name (e.g., 'users', 'items')
     * @param int|string $id     The record ID
     * @return mixed The cached record or null if not found
     */
    public function getRecord(string $entity, int|string $id): mixed
    {
        return $this->getRecordByColumn($entity, 'id', $id);
    }

    /**
     * Set an individual record in the cache by entity and ID.
     *
     * @param string     $entity   The entity name (e.g., 'users', 'items')
     * @param int|string $id       The record ID
     * @param mixed      $data     The record data to cache
     * @param int|null   $localTtl Optional TTL for this specific record (in seconds)
     */
    public function setRecord(string $entity, int|string $id, mixed $data, ?int $localTtl = null): void
    {
        $this->setRecordByColumn($entity, 'id', $id, $data, $localTtl);
    }

    /**
     * Invalidate an individual record in the cache by entity and ID.
     *
     * @param string     $entity The entity name (e.g., 'users', 'items')
     * @param int|string $id     The record ID
     */
    public function invalidateRecord(string $entity, int|string $id): void
    {
        $this->invalidateRecordByColumn($entity, 'id', $id);
    }

    /**
     * Get a cached list of records for an entity based on query parameters.
     * Uses versioning to invalidate old lists when data changes.
     *
     * @param string $entity The entity name (e.g., 'users', 'items')
     * @param array<string, mixed>  $query  The query parameters used to generate the list
     * @return mixed The cached list or null if not found
     */
    public function getList(string $entity, array $query): mixed
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        $data = $redis->get($key);
        return $data === null ? null : json_decode($data, true);
    }

    /**
     * Set a cached list of records for an entity based on query parameters.
     * Uses versioning to manage cache invalidation.
     *
     * @param string   $entity   The entity name (e.g., 'users', 'items')
     * @param array<string, mixed>    $query    The query parameters used to generate the list
     * @param mixed    $data     The list data to cache
     * @param int|null $localTtl Optional TTL for this specific list (in seconds)
     */
    public function setList(string $entity, array $query, mixed $data, ?int $localTtl = null): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        $redis->setex($key, $localTtl ?? $this->listTtl, json_encode($data));
    }

    /**
     * Invalidate all cached lists for an entity by incrementing its version.
     * This effectively makes all previous list caches obsolete.
     *
     * @param string $entity The entity name (e.g., 'users', 'items')
     */
    public function invalidateLists(string $entity): void
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $redis->incr($entity . ':version');
    }

    /**
     * Get the current list version for an entity.
     * If no version is set, it defaults to 1.
     *
     * @param string $entity The entity name (e.g., 'users', 'items')
     * @return int The current version number
     */
    private function getListVersion(string $entity): int
    {
        $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
        defer(fn () => $this->redisPool->put($redis));

        $versionKey = $entity . ':version';
        $version    = $redis->get($versionKey);

        if ($version === false || $version === null) {
            return 1;
        }

        return (int)$version;
    }

    /**
     * Generate a Redis key for a list of records based on entity, query parameters, and version.
     *
     * @param string $entity  The entity name (e.g., 'users', 'items')
     * @param array<string, mixed>  $query   The query parameters used to generate the list
     * @param int    $version The current version number for the entity
     * @return string The generated Redis key
     */
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
     * @param int      $keepVersions Number of recent versions to keep
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
     *
     */
    private function extractVersionFromKey(string $key): int
    {
        if (preg_match('/v(\d+):/', $key, $matches)) {
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
