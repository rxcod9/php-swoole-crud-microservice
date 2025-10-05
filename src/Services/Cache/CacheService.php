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
    public function __construct(
        private TableCacheService $tableCacheService,
        private RedisCacheService $redisCacheService
    ) {
        //
    }

    /* ---------------------------
     * RECORDS
     * ---------------------------
     */

    /* ---------------------------
     * RECORDS
     * ---------------------------
     */
    public function get(string $key): mixed
    {
        // 1. Check local table cache first
        $value = $this->tableCacheService->get($key);
        if ($value !== null) {
            return [TableCacheService::TAG, $value];
        }

        // 2. Fallback to Redis
        $value = $this->redisCacheService->get($key);
        if ($value !== null) {
            // warm local cache for faster next access
            try {
                $this->tableCacheService->set($key, $value, 120);
            } catch (Throwable $e) {
                error_log('Exception: ' . $e->getMessage()); // logged internally
            }

            return [RedisCacheService::TAG, $value];
        }

        return [null, $value];
    }

    public function set(string $key, mixed $data, ?int $localTtl = null): void
    {
        // Write-through both caches
        try {
            $this->tableCacheService->set($key, $data, $localTtl);
        } catch (Throwable $throwable) {
            error_log('Exception: ' . $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->set($key, $data, $localTtl);
    }

    public function incrBy(string $key, string $column, int $increment, ?int $localTtl = null): int|float
    {
        // Write-through both caches
        $tableCount = 0;
        try {
            $tableCount = $this->tableCacheService->incr($key, $column, $increment);
        } catch (Throwable $throwable) {
            error_log('Exception: ' . $throwable->getMessage()); // logged internally
        }

        $redisCount = $this->redisCacheService->incrBy($key, $increment, $localTtl);

        return max($tableCount, $redisCount);
    }

    public function incr(string $key, string $column, ?int $localTtl = null): int|float
    {
        return $this->incrBy($key, $column, 1, $localTtl);
    }

    /* ---------------------------
     * RECORDS
     * ---------------------------
     */
    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        // 1. Check local table cache first
        $data = $this->tableCacheService->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            return [TableCacheService::TAG, $data];
        }

        // 2. Fallback to Redis
        $data = $this->redisCacheService->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            // warm local cache for faster next access
            try {
                $this->tableCacheService->setRecordByColumn($entity, $column, $value, $data);
            } catch (Throwable $e) {
                error_log('Exception: ' . $e->getMessage()); // logged internally
            }

            return [RedisCacheService::TAG, $data];
        }

        return [null, $data];
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data, ?int $localTtl = null): void
    {
        // Write-through both caches
        try {
            $this->tableCacheService->setRecordByColumn($entity, $column, $value, $data, $localTtl);
        } catch (Throwable $throwable) {
            error_log('Exception: ' . $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->setRecordByColumn($entity, $column, $value, $data, $localTtl);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        try {
            $this->tableCacheService->invalidateRecordByColumn($entity, $column, $value);
        } catch (Throwable $throwable) {
            error_log('Exception: ' . $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->invalidateRecordByColumn($entity, $column, $value);
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
     * LISTS
     * ---------------------------
     */

    public function getList(string $entity, array $query): mixed
    {
        $value = $this->tableCacheService->getList($entity, $query);
        if ($value !== null) {
            return [TableCacheService::TAG, $value];
        }

        $value = $this->redisCacheService->getList($entity, $query);
        if ($value !== null) {
            try {
                $this->tableCacheService->setList($entity, $query, $value);
            } catch (Throwable $e) {
                error_log('Exception: ' . $e->getMessage()); // logged internally
            }

            return [RedisCacheService::TAG, $value];
        }

        return [null, $value];
    }

    public function setList(string $entity, array $query, mixed $data, ?int $localTtl = null): void
    {
        try {
            $this->tableCacheService->setList($entity, $query, $data, $localTtl);
        } catch (Throwable $throwable) {
            error_log('Exception: ' . $throwable->getMessage()); // logged internally
        }

        $this->redisCacheService->setList($entity, $query, $data, $localTtl);
    }

    public function invalidateLists(string $entity): void
    {
        $this->tableCacheService->invalidateLists($entity);
        $this->redisCacheService->invalidateLists($entity);
    }

    /**
     * Garbage collect old list versions for an entity.
     *
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
        $this->redisCacheService->gc();
    }
}
