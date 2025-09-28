<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Exception;

/**
 * CacheService
 *
 * Composite cache service using both local Swoole Table and Redis.
 * Implements a two-level caching strategy for optimal performance.
 *
 * @package App\Services
 */
final class CacheService
{
    public function __construct(
        private TableCacheService $tableCache,
        private RedisCacheService $redisCache
    ) {
        //
    }

    /* ---------------------------
     * RECORDS
     * ---------------------------
     */
    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        // 1. Check local table cache first
        $data = $this->tableCache->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            return [TableCacheService::TAG, $data];
        }

        // 2. Fallback to Redis
        $data = $this->redisCache->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            // warm local cache for faster next access
            try {
                $this->tableCache->setRecordByColumn($entity, $column, $value, $data);
            } catch (Exception $e) {
                error_log('Exception: ' . $e->getMessage()); // logged internally
            }
            return [RedisCacheService::TAG, $data];
        }

        return [null, $data];
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data): void
    {
        // Write-through both caches
        try {
            $this->tableCache->setRecordByColumn($entity, $column, $value, $data);
        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage()); // logged internally
        }
        $this->redisCache->setRecordByColumn($entity, $column, $value, $data);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        try {
            $this->tableCache->invalidateRecordByColumn($entity, $column, $value);
        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage()); // logged internally
        }
        $this->redisCache->invalidateRecordByColumn($entity, $column, $value);
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
     * LISTS
     * ---------------------------
     */

    public function getList(string $entity, array $query): mixed
    {
        $value = $this->tableCache->getList($entity, $query);
        if ($value !== null) {
            return [TableCacheService::TAG, $value];
        }

        $value = $this->redisCache->getList($entity, $query);
        if ($value !== null) {
            try {
                $this->tableCache->setList($entity, $query, $value);
            } catch (Exception $e) {
                error_log('Exception: ' . $e->getMessage()); // logged internally
            }
            return [RedisCacheService::TAG, $value];
        }

        return [null, $value];
    }

    public function setList(string $entity, array $query, mixed $data): void
    {
        try {
            $this->tableCache->setList($entity, $query, $data);
        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage()); // logged internally
        }
        $this->redisCache->setList($entity, $query, $data);
    }

    public function invalidateLists(string $entity): void
    {
        $this->tableCache->invalidateLists($entity);
        $this->redisCache->invalidateLists($entity);
    }

    /**
     * Garbage collect old list versions for an entity.
     *
     * @param int $keepVersions Number of latest versions to keep
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        // Table cache cleanup
        $this->tableCache->gcOldListVersions($entities, $keepVersions);

        // Redis cache cleanup
        $this->redisCache->gcOldListVersions($entities, $keepVersions);
    }

    /**
     * Garbage collect old list versions for an entity.
     *
     */
    public function gc(): void
    {
        // Table cache cleanup
        $this->tableCache->gc();

        // Redis cache cleanup
        $this->redisCache->gc();
    }
}
