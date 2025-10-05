<?php

/**
 * src/Services/Cache/TableCacheService.php
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
    public const string TAG = 'TABLE';

    public function __construct(
        private TableWithLRUAndGC $tableWithLRUAndGC,
        private int $recordTtl = 300,
        private int $listTtl = 120,
    ) {
        //
    }

    public function get(string $key): mixed
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
        $row = $this->tableWithLRUAndGC->get($key);
        if (!$row) {
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

    public function set(string $key, mixed $value, int $ttl): bool
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

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

    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $key   = $this->recordKeyByColumn($entity, $column, $value);
        $value = $this->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data, ?int $localTtl = null): bool
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->set($key, json_encode($data), $localTtl ?? $this->recordTtl);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): bool
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->tableWithLRUAndGC->del($key);
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

    /**
     * ---------------------------
     * LIST CACHE (versioned)
     * ---------------------------
     */
    public function getList(string $entity, array $query): mixed
    {
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);
        $value   = $this->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function setList(string $entity, array $query, mixed $data, ?int $localTtl = null): void
    {
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);
        $this->set($key, json_encode($data), $localTtl ?? $this->listTtl);
    }

    public function invalidateLists(string $entity): void
    {
        $key = $entity . ':version';
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $row = $this->tableWithLRUAndGC->get($key);
        if (!$row) {
            $this->tableWithLRUAndGC->set($key, [
                'value'       => 1,
                'expires_at'  => Carbon::now()->getTimestamp() + 86400, // keep version for a day
                'last_access' => Carbon::now()->getTimestamp(), // keep version for a day
            ]);
            return;
        }

        $version = (int) ($row['value'] ?? 0);
        $this->tableWithLRUAndGC->set($key, [
            'value'       => $version + 1,
            'expires_at'  => Carbon::now()->getTimestamp() + 86400, // keep version for a day
            'last_access' => Carbon::now()->getTimestamp(), // keep version for a day
        ]);
    }

    private function getListVersion(string $entity): int
    {
        $key = $entity . ':version';
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $row = $this->tableWithLRUAndGC->get($key);

        if (!$row) {
            return 1;
        }

        return (int) ($row['value'] ?? 1);
    }

    /**
     * Generate cache keys for lists.
     *
     * @param string            $entity  Entity name
     * @param array<int, mixed> $query   Query parameters
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
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        return $key;
    }

    public function incr(string $key, string $column, int|float $incrby = 1): int|float
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        return $this->tableWithLRUAndGC->incr($key, $column, $incrby);
    }

    /**
     * Update last_access timestamp if column exists.
     */
    /**
     * Update last_access timestamp if enough time has passed.
     * Only writes to the table when necessary.
     */
    private function touch(string $key, array $row, ?int $now = null): array
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $now ??= Carbon::now()->getTimestamp();

        // Only update if last_access is different to avoid redundant writes
        if (($row['last_access'] ?? 0) === $now) {
            return $row;
        }

        $row['last_access'] = $now;
        $row['usage']       = ($row['usage'] ?? 0) + 1;

        // Set row back to table, but avoid logging flood
        if (!$this->tableWithLRUAndGC->set($key, $row)) {
            error_log('Failed to touch key: ' . $key);
        }

        return $row;
    }

    /**
     * Garbage collect old list versions for multiple entities in one loop
     *
     * @param string[] $entities
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
                $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
                $this->tableWithLRUAndGC->del($key);
            }
        }
    }

    private function getEntityVersions(array $entities): array
    {
        $versions = [];
        foreach ($entities as $entity) {
            $versions[$entity] = $this->getListVersion($entity);
        }

        return $versions;
    }

    private function matchEntity(string $key, array $entities): ?string
    {
        foreach ($entities as $entity) {
            if (str_starts_with($key, $entity . ':list:v')) {
                return $entity;
            }
        }

        return null;
    }

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
