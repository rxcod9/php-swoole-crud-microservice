<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Exceptions\CacheSetException;
use App\Tables\TableWithLRUAndGC;

use function strlen;

use Swoole\Table;

/**
 * TableCacheService
 *
 * A caching service that uses Swoole Table to cache individual records and
 * versioned lists of records. It provides methods to get, set, and
 * invalidate caches for both records and lists.
 */
final class TableCacheService
{
    public const TAG = 'TABLE';

    public function __construct(
        private TableWithLRUAndGC $table,
        private int $recordTtl = 300,
        private int $listTtl = 120,
        private int $lastAccessThrottle = 5,
    ) {
        //
    }

    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->getWithTtl($key);
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data): bool
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->setWithTtl($key, $data, $this->recordTtl);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): bool
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->table->del($key);
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
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);
        return $this->getWithTtl($key);
    }

    public function setList(string $entity, array $query, mixed $data): void
    {
        $version = $this->getListVersion($entity);
        $key     = $this->listKey($entity, $query, $version);
        $this->setWithTtl($key, $data, $this->listTtl);
    }

    public function invalidateLists(string $entity): bool
    {
        $key = "{$entity}:version";
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $row = $this->table->get($key);
        if (!$row) {
            return $this->table->set($key, [
                'value'       => json_encode(['version' => 1]),
                'expires_at'  => time() + 86400, // keep version for a day
                'last_access' => time(), // keep version for a day
            ]);
        }

        $data    = json_decode($row['value'] ?? '{}', true);
        $version = (int)($data['version'] ?? 1);
        return $this->table->set($key, [
            'value'       => json_encode(['version' => (int)($version + 1)]),
            'expires_at'  => time() + 86400, // keep version for a day
            'last_access' => time(), // keep version for a day
        ]);
    }

    private function getListVersion(string $entity): int
    {
        $key = "{$entity}:version";
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $row = $this->table->get($key);

        if (!$row) {
            // $this->table->set($versionKey, [
            //     'value'      => '1',
            //     'expires_at' => time() + 86400,
            // ]);
            return 1;
        }

        $data = json_decode($row['value'], true);
        return (int)($data['version'] ?? 1);
    }

    /**
     * Generate cache keys for lists.
     *
     * @param string $entity Entity name
     * @param array $query Query parameters
     * @param int $version List version
     *
     */
    private function listKey(string $entity, array $query, int $version): string
    {
        ksort($query); // normalize query
        $queryString = http_build_query($query);

        // Redis can use full SHA-256
        $hash = hash('sha256', $queryString);

        $key = "{$entity}:list:v{$version}:{$hash}";

        // For Swoole Table safety, truncate if still >64 bytes
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        return $key;
    }


    /* ---------------------------
     * Internal helpers
     * ---------------------------
     */

    private function getWithTtl(string $key): mixed
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
        $row = $this->table->get($key);
        if (!$row) {
            return null;
        }

        // check expiration
        $now = time();
        if ((int)$row['expires_at'] < $now) {
            $this->table->del($key);
            return null;
        }

        if (isset($row['last_access']) && ($now - $row['last_access']) >= $this->lastAccessThrottle) {
            $this->touch($key, $row);
        }

        return json_decode($row['value'], true);
    }

    private function setWithTtl(string $key, mixed $data, int $ttl): bool
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $success = $this->table->set($key, [
            'value'       => json_encode($data),
            'expires_at'  => time() + $ttl,
            'last_access' => time(),
        ]);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        return $success;
    }

    /**
     * Update last_access timestamp if column exists.
     */
    /**
     * Update last_access timestamp if enough time has passed.
     * Only writes to the table when necessary.
     */
    protected function touch(string $key, array $row, ?int $now = null): array
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;

        $now ??= time();

        // Only update if last_access is different to avoid redundant writes
        if (($row['last_access'] ?? 0) === $now) {
            return $row;
        }

        $row['last_access'] = $now;

        // Set row back to table, but avoid logging flood
        if (!$this->table->set($key, $row)) {
            error_log("Failed to touch key: $key");
        }

        return $row;
    }

    /**
     * Garbage collect old list versions for multiple entities in one loop
     *
     * @param string[] $entities
     *
     */
    public function gcOldListVersions(array $entities, int $keepVersions = 2): void
    {
        $versions = $this->getEntityVersions($entities);
        foreach ($this->table as $key => $row) {
            $entity = $this->matchEntity($key, $entities);
            if (!$entity) {
                continue;
            }

            $version = $this->extractVersion($key);
            if ($version <= $versions[$entity] - $keepVersions) {
                $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
                $this->table->del($key);
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
            if (str_starts_with($key, "{$entity}:list:v")) {
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
     *
     */
    public function gc(): void
    {
        $this->gcOldListVersions(['users']);
        $this->table->gc();
    }
}
