<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Tables\TableWithLRUAndGC;
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
    public function __construct(
        private TableWithLRUAndGC $table,
        private int $recordTtl = 300,
        private int $listTtl = 120
    ) {
        //
    }

    public function getRecordByColumn(string $entity, string $column, int|string $value): mixed
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        return $this->getWithTtl($key);
    }

    public function setRecordByColumn(string $entity, string $column, int|string $value, mixed $data): void
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        $this->setWithTtl($key, $data, $this->recordTtl);
    }

    public function invalidateRecordByColumn(string $entity, string $column, int|string $value): void
    {
        $key = $this->recordKeyByColumn($entity, $column, $value);
        $this->table->del($key);
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
        $key = $this->listKey($entity, $query, $version);
        return $this->getWithTtl($key);
    }

    public function setList(string $entity, array $query, mixed $data): void
    {
        $version = $this->getListVersion($entity);
        $key = $this->listKey($entity, $query, $version);
        $this->setWithTtl($key, $data, $this->listTtl);
    }

    public function invalidateLists(string $entity): void
    {
        $versionKey = "{$entity}:list:version";
        $row = $this->table->get($versionKey);
        $version = $row ? (int)$row['value'] : 1;
        $this->table->set($versionKey, [
            'value'      => (string)($version + 1),
            'expires_at' => time() + 86400, // keep version for a day
        ]);
    }

    private function getListVersion(string $entity): int
    {
        $versionKey = "{$entity}:list:version";
        $row = $this->table->get($versionKey);

        if (!$row) {
            $this->table->set($versionKey, [
                'value'      => '1',
                'expires_at' => time() + 86400,
            ]);
            return 1;
        }

        return (int)$row['value'];
    }

    private function listKey(string $entity, array $query, int $version): string
    {
        ksort($query); // normalize params
        $queryString = http_build_query($query);
        return "{$entity}:list:v{$version}:" . md5($queryString);
    }

    /* ---------------------------
     * Internal helpers
     * ---------------------------
     */

    private function getWithTtl(string $key): mixed
    {
        $row = $this->table->get($key);
        if (!$row) {
            return null;
        }

        if ((int)$row['expires_at'] < time()) {
            $this->table->del($key);
            return null;
        }

        return json_decode($row['value'], true);
    }

    private function setWithTtl(string $key, mixed $data, int $ttl): void
    {
        $this->table->set($key, [
            'value'      => json_encode($data),
            'expires_at' => time() + $ttl,
        ]);
    }
}
