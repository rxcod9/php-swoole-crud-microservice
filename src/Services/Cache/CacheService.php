<?php

/**
 * src/Services/Cache/CacheService.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/Cache/CacheService.php
 */
declare(strict_types=1);

namespace App\Services\Cache;

use Throwable;

/**
 * CacheService
 * Composite cache service using both local Swoole Table and Redis.
 * Implements a two-level caching strategy for optimal performance.
 *
 * @category  Services
 * @package   App\Services\Cache
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class CacheService
{
    /**
     * CacheService tag for logging and debugging.
     */
    public const TAG = 'CacheService';

    /**
     * Constructor
     *
     * @param TableCacheService $tableCacheService Local Swoole Table cache service
     * @param RedisCacheService $redisCacheService  Redis cache service
     */
    public function __construct(
        private TableCacheService $tableCacheService,
        private RedisCacheService $redisCacheService
    ) {
        // Empty Constructor
    }

    /**
     * Get a cached value by key.
     * Checks local table cache first, then falls back to Redis.
     * If found in Redis, warms the local cache for faster subsequent access.
     *
     * @param string $key The cache key
     * @return array{0: mixed, 1: string|null} Tuple of (data, cache type)
     */
    public function get(string $key): mixed
    {
        // 1. Check local table cache first
        $value = $this->tableCacheService->get($key);
        if ($value !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE HIT: TABLE'); // logged internally
            return [$value, TableCacheService::CACHE_TYPE];
        }

        // 2. Fallback to Redis
        $value = $this->redisCacheService->get($key);
        if ($value !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE HIT: REDIS'); // logged internally
            // warm local cache for faster next access
            try {
                $this->tableCacheService->set($key, $value, 120);
            } catch (Throwable $e) {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $e->getMessage()); // logged internally
            }

            return [$value, RedisCacheService::CACHE_TYPE];
        }

        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE MISS'); // logged internally
        return [$value, null];
    }

    /**
     * Set a cached value by key.
     * Writes through both local table cache and Redis.
     *
     * @param string $key The cache key
     * @param mixed $data The data to cache
     * @param int|null $localTtl Optional TTL for local cache in seconds
     */
    public function set(string $key, mixed $data, ?int $localTtl = null): void
    {
        // Write-through both caches
        try {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE SET: TABLE'); // logged internally
            $this->tableCacheService->set($key, $data, $localTtl);
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
        }

        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE SET: REDIS'); // logged internally
        $this->redisCacheService->set($key, $data, $localTtl);
    }

    /**
     * Increment a numeric cached value by a specified amount.
     * Writes through both local table cache and Redis.
     * Returns the maximum value from both caches to ensure consistency.
     *
     * @param string $key The cache key
     * @param string $column The column name (for table cache)
     * @param int $increment The amount to increment by
     * @param int|null $localTtl Optional TTL for local cache in seconds
     * @return int|float The new incremented value
     */
    public function incrBy(string $key, string $column, int $increment, ?int $localTtl = null): int|float
    {
        // Write-through both caches
        $tableCount = 0;
        try {
            $tableCount = $this->tableCacheService->incr($key, $column, $increment);
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
        }

        $redisCount = $this->redisCacheService->incrBy($key, $increment, $localTtl);

        return max($tableCount, $redisCount);
    }

    /**
     * Increment a numeric cached value by 1.
     * Writes through both local table cache and Redis.
     * Returns the maximum value from both caches to ensure consistency.
     *
     * @param string $key The cache key
     * @param string $column The column name (for table cache)
     * @param int|null $localTtl Optional TTL for local cache in seconds
     * @return int|float The new incremented value
     */
    public function incr(string $key, string $column, ?int $localTtl = null): int|float
    {
        return $this->incrBy($key, $column, 1, $localTtl);
    }

    /**
     * Get a cached record for an entity by a specific column and value.
     * Checks local table cache first, then falls back to Redis.
     * If found in Redis, warms the local cache for faster subsequent access.
     *
     * @param string $entity The entity name
     * @param string $column The column name to query
     * @param int|string $value The value to match
     * @return array{0: mixed, 1: string|null} Tuple of (data, cache type)
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        // 1. Check local table cache first
        $data = $this->tableCacheService->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE HIT: TABLE'); // logged internally
            return [$data, TableCacheService::CACHE_TYPE];
        }

        // 2. Fallback to Redis
        $data = $this->redisCacheService->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE HIT: REDIS'); // logged internally
            // warm local cache for faster next access
            try {
                $this->tableCacheService->setRecordByColumn(CacheRecordParams::fromArray([
                    'entity' => $entity,
                    'column' => $column,
                    'value'  => $value,
                    'data'   => $data,
                ]));
            } catch (Throwable $e) {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $e->getMessage()); // logged internally
            }

            return [$data, RedisCacheService::CACHE_TYPE];
        }

        return [$data, null];
    }

    /**
     * Set a cached record for an entity by a specific column and value.
     * Writes through both local table cache and Redis.
     *
     * @param CacheRecordParams $cacheRecordParams The cache record parameters
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function setRecordByColumn(CacheRecordParams $cacheRecordParams): void
    {
        // Write-through both caches
        try {
            $this->tableCacheService->setRecordByColumn($cacheRecordParams);
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->setRecordByColumn($cacheRecordParams);
    }

    /**
     * Invalidate a cached record for an entity by a specific column and value.
     * Removes the record from both local table cache and Redis.
     *
     * @param string $entity The entity name
     * @param string $column The column name to identify the record
     * @param int|string $value The value of the column to identify the record
     */
    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        try {
            $this->tableCacheService->invalidateRecordByColumn($entity, $column, $value);
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->invalidateRecordByColumn($entity, $column, $value);
    }

    /**
     * Get a cached record for an entity by its ID.
     *
     * @param string $entity The entity name
     * @param int|string $id The record ID
     * @return array{0: mixed, 1: string|null} Tuple of (data, cache type)
     */
    public function getRecord(string $entity, int|string $id): mixed
    {
        return $this->getRecordByColumn($entity, 'id', $id);
    }

    /**
     * Set a cached record for an entity by its ID.
     *
     * @param string $entity The entity name
     * @param int|string $id The record ID
     * @param mixed $data The data to cache
     * @param int|null $localTtl Optional TTL for local cache in seconds
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
     * Invalidate a cached record for an entity by its ID.
     *
     * @param string $entity The entity name
     * @param int|string $id The record ID
     */
    public function invalidateRecord(string $entity, int|string $id): void
    {
        $this->invalidateRecordByColumn($entity, 'id', $id);
    }

    /**
     * Get a cached list of records for an entity based on query parameters.
     *
     * @param string $entity The entity name
     * @param array<string, mixed> $query The query parameters (filters, pagination, sorting)
     *
     * @return array{0: mixed, 1: string|null} Tuple of (data, cache type)
     */
    public function getList(string $entity, array $query): array
    {
        $value = $this->tableCacheService->getList($entity, $query);
        if ($value !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE HIT: TABLE'); // logged internally
            return [$value, TableCacheService::CACHE_TYPE];
        }

        $value = $this->redisCacheService->getList($entity, $query);
        if ($value !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'CACHE HIT: REDIS'); // logged internally
            try {
                $this->tableCacheService->setList($entity, $query, $value);
            } catch (Throwable $e) {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $e->getMessage()); // logged internally
            }

            return [$value, RedisCacheService::CACHE_TYPE];
        }

        return [$value, null];
    }

    /**
     * Set a cached list of records for an entity based on query parameters.
     * Writes through both local table cache and Redis.
     *
     * @param string $entity The entity name
     * @param array<string, mixed> $query The query parameters (filters, pagination, sorting)
     * @param mixed $data The data to cache
     * @param int|null $localTtl Optional TTL for local cache in seconds
     */
    public function setList(string $entity, array $query, mixed $data, ?int $localTtl = null): void
    {
        try {
            $this->tableCacheService->setList($entity, $query, $data, $localTtl);
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->setList($entity, $query, $data, $localTtl);
    }

    /**
     * Invalidate cached lists for an entity.
     * Removes all cached lists for the entity from both local table cache and Redis.
     *
     * @param string $entity The entity name
     */
    public function invalidateLists(string $entity): void
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . ']', 'Invalidating lists for entity: ' . $entity);
        $this->tableCacheService->invalidateLists($entity);
        $this->redisCacheService->invalidateLists($entity);
    }

    /**
     * Garbage collect old list versions for an entity.
     *
     * @param array<string> $entities List of entity names to clean up
     * @param int $keepVersions Number of latest versions to keep
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        // Table cache cleanup
        $this->tableCacheService->gcOldListVersions($entities, $keepVersions);

        // Redis cache cleanup
        $this->redisCacheService->gcOldListVersions($entities, $keepVersions);
    }

    /**
     * Garbage collect old list versions for an entity.
     */
    public function gc(): void
    {
        // Table cache cleanup
        $this->tableCacheService->gc();

        // Redis cache cleanup
        // Let it evict by redis policy
        // $this->redisCacheService->gc();
    }
}
