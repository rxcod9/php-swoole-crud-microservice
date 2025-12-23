<?php

/**
 * src/Services/Cache/TableCacheService.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/Cache/TableCacheService.php
 */
declare(strict_types=1);

namespace App\Services\Cache;

use App\Exceptions\CacheSetException;
use App\Tables\TableWithLRUAndGC;
use Carbon\Carbon;

/**
 * TableCacheService
 * A caching service that uses Swoole Table to cache individual records and
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
final readonly class TableCacheService
{
    /**
     * Cache type identifier
     */
    public const CACHE_TYPE = 'Table';

    /**
     * Tag for logging
     */
    public const TAG = 'TableCacheService';

    /**
     * Constructor
     *
     * @param TableWithLRUAndGC<string, array<string, mixed>> $tableWithLRUAndGC Swoole Table with LRU and GC
     * @param int               $recordTtl         Default TTL for individual records (in seconds)
     * @param int               $listTtl           Default TTL for lists (in seconds)
     */
    public function __construct(
        private TableWithLRUAndGC $tableWithLRUAndGC,
        private int $recordTtl = 5 * 60 * 10,
        private int $listTtl = 2 * 60 * 10
    ) {
        // Empty Constructor
    }

    /**
     * Get a value from the cache by key.
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found or expired
     */
    public function get(string $key): mixed
    {
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;
        $row = $this->tableWithLRUAndGC->get($key);
        if ($row === null) {
            return null;
        }

        // check expiration
        $now = Carbon::now()->getTimestamp();
        if ((int)$row['expires_at'] < $now) {
            $this->tableWithLRUAndGC->del($key);
            return null;
        }

        // if (isset($row['last_access']) && ($now - $row['last_access']) >= $this->lastAccessThrottle) {
        $this->touch($key, $row);
        // }

        return $row['value'];
    }

    /**
     * Set a value in the cache with a TTL.
     *
     * @param string $key   Cache key
     * @param mixed  $value Value to cache
     * @param int    $ttl   Time to live in seconds
     * @return bool True on success, false on failure
     * @throws CacheSetException If unable to set the cache
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;

        $row = [
            'value'       => $value,
            'expires_at'  => Carbon::now()->getTimestamp() + $ttl,
            'last_access' => Carbon::now()->getTimestamp(),
        ];
        $success = $this->tableWithLRUAndGC->set($key, $row);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        return $success;
    }

    /**
     * Get a cached record by a specific column value.
     *
     * @param string          $entity Entity name (e.g., 'users')
     * @param string          $column Column name (e.g., 'id', 'email')
     * @param int|string      $value  Column value to look up
     * @return array<string, mixed>|null Cached record as associative array or null if not found
     */
    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $key   = $this->recordKeyByColumn($entity, $column, $value);
        $value = $this->get($key);
        return $value !== null ? json_decode($value, true) : null;
    }

    /**
     * Set a cached record by a specific column value.
     *
     * @param CacheRecordParams $cacheRecordParams DTO containing all cache record info
     * @return bool True on success, false on failure
     */
    public function setRecordByColumn(CacheRecordParams $cacheRecordParams): bool
    {
        $key = $this->recordKeyByColumn($cacheRecordParams->entity, $cacheRecordParams->column, $cacheRecordParams->value);
        return $this->set($key, json_encode($cacheRecordParams->data), $cacheRecordParams->ttl ?? $this->recordTtl);
    }

    /**
     * Invalidate a cached record by a specific column value.
     *
     * @param string          $entity Entity name (e.g., 'users')
     * @param string          $column Column name (e.g., 'id', 'email')
     * @param int|string      $value  Column value to look up
     * @return bool True on success, false on failure
     */
    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): bool
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->tableWithLRUAndGC->del($key);
    }

    /**
     * Generate cache key for a record by column.
     *
     * @param string     $entity Entity name
     * @param string     $column Column name
     * @param int|string $value  Column value
     * @return string Cache key
     */
    private function recordKeyByColumn(string $entity, string $column, int|string $value): string
    {
        return sprintf('%s:record:%s:%s', $entity, $column, $value);
    }

    /**
     * Get a cached record by its ID.
     * This is a convenience method that calls getRecordByColumn with 'id' as the column.
     *
     * @param string     $entity Entity name (e.g., 'users')
     * @param int|string $id     Record ID
     * @return array<string, mixed>|null Cached record as associative array or null if not found
     */
    public function getRecord(string $entity, int|string $id): mixed
    {
        return $this->getRecordByColumn($entity, 'id', $id);
    }

    /**
     * Set a cached record by its ID.
     * This is a convenience method that calls setRecordByColumn with 'id' as the column.
     *
     * @param string          $entity   Entity name (e.g., 'users')
     * @param int|string      $id       Record ID
     * @param mixed           $data     Record data to cache (associative array)
     * @param int|null        $localTtl Optional TTL for this record (in seconds). Defaults to service's recordTtl.
     * @throws CacheSetException If unable to set the cache
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
     * Invalidate a cached record by its ID.
     * This is a convenience method that calls invalidateRecordByColumn with 'id' as the column.
     *
     * @param string     $entity Entity name (e.g., 'users')
     * @param int|string $id     Record ID
     */
    public function invalidateRecord(string $entity, int|string $id): void
    {
        $this->invalidateRecordByColumn($entity, 'id', $id);
    }

    /**
     * Get a cached list of records based on query parameters.
     *
     * @param string            $entity Entity name (e.g., 'users')
     * @param array<string, mixed> $query  Query parameters (e.g., ['page' => 1, 'limit' => 10])
     * @return mixed Cached list of records or null if not found
     */
    public function getList(string $entity, array $query): mixed
    {
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);
        $value   = $this->get($key);
        return $value !== null ? json_decode($value, true) : null;
    }

    /**
     * Set a cached list of records based on query parameters.
     *
     * @param string            $entity   Entity name (e.g., 'users')
     * @param array<string, mixed> $query    Query parameters (e.g., ['page' => 1, 'limit' => 10])
     * @param mixed             $data     List of records to cache (array)
     * @param int|null         $localTtl Optional TTL for this list (in seconds). Defaults to service's listTtl.
     * @throws CacheSetException If unable to set the cache
     */
    public function setList(string $entity, array $query, mixed $data, ?int $localTtl = null): void
    {
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);
        $this->set($key, json_encode($data), $localTtl ?? $this->listTtl);
    }

    /**
     * Invalidate all cached lists for an entity by incrementing its version.
     * This effectively invalidates all existing list caches without deleting them.
     *
     * @param string $entity Entity name (e.g., 'users')
     */
    public function invalidateLists(string $entity): void
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Invalidating lists for entity: ' . $entity);
        $key = $entity . ':version';
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Version key: ' . $key);

        $row = $this->tableWithLRUAndGC->get($key);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Current version row: ' . json_encode($row));
        if ($row !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Incrementing version for key: ' . $key . ' value: ' . (((int)$row['value']) + 1));
            $this->tableWithLRUAndGC->set($key, [
                'value'       => ((int)$row['value']) + 1,
                'expires_at'  => Carbon::now()->getTimestamp() + 86400, // keep version for a day
                'last_access' => Carbon::now()->getTimestamp(), // keep version for a day
            ]);
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Version incremented for key: ' . $key . ' value: ' . (((int)$row['value']) + 1));
            return;
        }

        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Setting initial version for key: ' . $key . ' value: 1');
        $this->tableWithLRUAndGC->set($key, [
            'value'       => 1,
            'expires_at'  => Carbon::now()->getTimestamp() + 86400, // keep version for a day
            'last_access' => Carbon::now()->getTimestamp(), // keep version for a day
        ]);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Initial version set for key: ' . $key . ' value: 1');
    }

    /**
     * Get the current list version for an entity.
     * If no version exists, defaults to 1.
     *
     * @param string $entity Entity name (e.g., 'users')
     * @return int Current list version
     */
    private function getListVersion(string $entity): int
    {
        $key = $entity . ':version';
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;

        $row = $this->tableWithLRUAndGC->get($key);

        if ($row === null) {
            return 1;
        }

        return (int) ($row['value'] ?? 1);
    }

    /**
     * Generate cache keys for lists.
     *
     * @param string            $entity  Entity name
     * @param array<string, mixed> $query   Query parameters
     * @param int               $version List version
     */
    private function listKey(string $entity, array $query, int $version): string
    {
        ksort($query); // normalize query
        $queryString = http_build_query($query);

        // Redis can use full SHA-256
        $hash = hash('sha256', $queryString);

        $key = sprintf('%s:list:v%d:%s', $entity, $version, $hash);

        // For Swoole Table safety, truncate if still >64 bytes
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;

        return $key;
    }

    /**
     * Increment a numeric column in the cache.
     *
     * @param string     $key    Cache key
     * @param string     $column Column name to increment
     * @param int|float $incrby Amount to increment by (default is 1)
     * @return int|float New value after increment
     */
    public function incr(string $key, string $column, int|float $incrby = 1): int|float
    {
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;

        return $this->tableWithLRUAndGC->incr($key, $column, $incrby);
    }

    /**
     * Update last_access timestamp if column exists.
     *
     * @param string     $key Cache key
     * @param array<string, mixed> $row Current row data
     * @param int|null   $now Optional current timestamp to use
     * @return array<string, mixed> Updated row data
     */
    private function touch(string $key, array $row, ?int $now = null): array
    {
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;

        $now ??= Carbon::now()->getTimestamp();

        // Only update if last_access is different to avoid redundant writes
        if (($row['last_access'] ?? 0) === $now) {
            return $row;
        }

        $row['last_access'] = $now;
        $row['usage']       = ($row['usage'] ?? 0) + 1;

        // Set row back to table, but avoid logging flood
        if (!$this->tableWithLRUAndGC->set($key, $row)) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'Failed to touch key: ' . $key);
        }

        return $row;
    }

    /**
     * Garbage collect old list versions for multiple entities in one loop
     *
     * @param string[] $entities
     * @param int      $keepVersions Number of recent versions to keep (default is 2)
     *
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        $versions = $this->getEntityVersions($entities);
        foreach ($this->tableWithLRUAndGC as $key => $row) {
            $entity = $this->matchEntity($key, $entities);
            if ($entity === null) {
                continue;
            }

            if ($entity === '') {
                continue;
            }

            if ($entity === '0') {
                continue;
            }

            $version = $this->extractVersion($key);
            if ($version <= $versions[$entity] - $keepVersions) {
                $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;
                $this->tableWithLRUAndGC->del($key);
            }
        }
    }

    /**
     * Get current list versions for multiple entities
     *
     * @param string[] $entities
     * @return array<string, int> Associative array of entity => version
     */
    private function getEntityVersions(array $entities): array
    {
        $versions = [];
        foreach ($entities as $entity) {
            $versions[$entity] = $this->getListVersion($entity);
        }

        return $versions;
    }

    /**
     * Match a key to an entity based on known entities
     *
     * @param string   $key      Cache key
     * @param string[] $entities List of known entities
     * @return string|null Matched entity or null if no match
     */
    private function matchEntity(string $key, array $entities): ?string
    {
        foreach ($entities as $entity) {
            if (str_starts_with($key, $entity . ':list:v')) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Extract version number from a cache key
     *
     * @param string $key Cache key
     * @return int Extracted version number or 0 if not found
     */
    private function extractVersion(string $key): int
    {
        preg_match('/v(\d+):/', $key, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 0;
    }

    /**
     * Garbage collect old list versions for an entity.
     */
    public function gc(): void
    {
        $this->gcOldListVersions(['users']);
        $this->tableWithLRUAndGC->gc();
    }
}
