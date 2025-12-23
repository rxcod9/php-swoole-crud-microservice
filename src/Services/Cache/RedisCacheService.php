<?php

/**
 * src/Services/Cache/RedisCacheService.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
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
use Redis;
use Throwable;

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
        private int $recordTtl = 5 * 60 * 10,
        private int $listTtl = 2 * 60 * 10
    ) {
        // Empty Constructor
    }

    /**
     * Get a value from the cache by key.
     *
     * @param string $key The cache key
     * @return mixed The cached value or null if not found
     */
    public function get(string $key): mixed
    {
        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis

            return $redis->get($key);
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
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
        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis

            $redis->setex($key, $localTtl ?? $this->recordTtl, $data);
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
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
        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis

            $count = $redis->incrBy($key, $increment);
            if ($count === $increment) {
                $redis->expire($key, $localTtl ?? $this->recordTtl);
            }

            return $count;
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
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

        return ($data === null || $data === false) ? null : json_decode($data, true);
    }

    /**
     * Set an individual record in the cache by entity, column, and value.
     *
     * @param CacheRecordParams $cacheRecordParams The cache record parameters
     */
    public function setRecordByColumn(CacheRecordParams $cacheRecordParams): void
    {
        $key = $this->recordKeyByColumn($cacheRecordParams->entity, $cacheRecordParams->column, $cacheRecordParams->value);
        $this->set($key, json_encode($cacheRecordParams->data), $cacheRecordParams->ttl ?? $this->recordTtl);
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
        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis

            $key = $this->recordKeyByColumn($entity, $column, $value);
            $redis->del($key);
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
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
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function setRecord(string $entity, int|string $id, mixed $data, ?int $localTtl = null): void
    {
        $this->setRecordByColumn(CacheRecordParams::fromArray([
            'entity' => $entity,
            'column' => 'id',
            'value'  => $id,
            'data'   => $data,
            'ttl'    => $localTtl,
        ]));
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
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
            $data  = $redis->get($key);
        } catch (\Throwable $throwable) {
            // Log error but do not throw, as cache failure should not break functionality
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Redis getList error: ' . $throwable->getMessage());
            return null;
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }

        return ($data === null || $data === false) ? null : json_decode($data, true);
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
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);

        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis
            $redis->setex($key, $localTtl ?? $this->listTtl, json_encode($data));
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
    }

    /**
     * Invalidate all cached lists for an entity by incrementing its version.
     * This effectively makes all previous list caches obsolete.
     *
     * @param string $entity The entity name (e.g., 'users', 'items')
     */
    public function invalidateLists(string $entity): void
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Invalidating lists for entity: ' . $entity);
        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Incrementing version for entity: ' . $entity);
            $result = $redis->incr($entity . ':version');
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Version incremented for entity: ' . $entity . ' result:' . var_export($result, true));
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
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
        try {
            $redis = $this->redisPool->get(); // returns Swoole\Coroutine\Redis

            $versionKey = $entity . ':version';
            $version    = $redis->get($versionKey);

            if ($version === false || $version === null) {
                return 1;
            }

            return (int)$version;
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
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
     * Scans Redis for keys matching pattern "*:list:v*", determines their entity and version,
     * and deletes any versions older than `$keepVersions` behind the current.
     *
     * @param string[] $entities
     * @param int      $keepVersions Number of recent versions to keep
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        $versions = $this->getCurrentVersions($entities);
        if ($versions === []) {
            return;
        }

        $redis = null;

        try {
            $redis  = $this->redisPool->get();
            $cursor = 0;

            do {
                $keys = $this->scanKeys($redis, $cursor);
                if ($keys === []) {
                    continue;
                }

                foreach ($keys as $key) {
                    $this->maybeDeleteOldVersion($redis, $key, $entities, $versions, $keepVersions);
                }
            } while ($cursor !== 0);
        } catch (Throwable $throwable) {
            logDebug('RedisCacheService::gcOldListVersions failed', $throwable->getMessage());
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }
    }

    /**
     * Wrapper around Redis SCAN to handle edge cases cleanly.
     *
     * @return string[] List of scanned keys.
     */
    private function scanKeys(Redis $redis, int &$cursor): array
    {
        $keys = $redis->scan($cursor, '*:list:v*', 100);
        return is_array($keys) ? $keys : [];
    }

    /**
     * Check if a Redis key belongs to a known entity and is safe to delete based on versioning.
     *
     * @param string[] $entities
     * @param array<string, int> $versions
     */
    private function maybeDeleteOldVersion(
        Redis $redis,
        string $key,
        array $entities,
        array $versions,
        int $keepVersions
    ): void {
        $entity = $this->findEntityForKey($key, $entities);
        if ($entity === null) {
            return;
        }

        $version = $this->extractVersionFromKey($key);
        $latest  = $versions[$entity] ?? 0;

        // Delete old list versions beyond the keep threshold
        if ($version <= ($latest - $keepVersions)) {
            $redis->del($key);
        }
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
