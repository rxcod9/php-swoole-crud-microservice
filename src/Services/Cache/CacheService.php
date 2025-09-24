<?php

namespace App\Services\Cache;

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
            return $data;
        }

        // 2. Fallback to Redis
        $data = $this->redisCache->getRecordByColumn($entity, $column, $value);
        if ($data !== null) {
            // warm local cache for faster next access
            $this->tableCache->setRecordByColumn($entity, $column, $value, $data);
        }

        return $value;
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data): void
    {
        // Write-through both caches
        $this->tableCache->setRecordByColumn($entity, $column, $value, $data);
        $this->redisCache->setRecordByColumn($entity, $column, $value, $data);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        $this->tableCache->invalidateRecordByColumn($entity, $column, $value);
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
            return $value;
        }

        $value = $this->redisCache->getList($entity, $query);
        if ($value !== null) {
            $this->tableCache->setList($entity, $query, $value);
        }

        return $value;
    }

    public function setList(string $entity, array $query, mixed $data): void
    {
        $this->tableCache->setList($entity, $query, $data);
        $this->redisCache->setList($entity, $query, $data);
    }

    public function invalidateLists(string $entity): void
    {
        $this->tableCache->invalidateLists($entity);
        $this->redisCache->invalidateLists($entity);
    }
}
