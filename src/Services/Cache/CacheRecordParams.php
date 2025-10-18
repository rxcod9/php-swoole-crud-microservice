<?php

/**
 * src/Services/Cache/CacheRecordParams.php
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
 * @since     2025-10-15
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/Cache/CacheRecordParams.php
 */
declare(strict_types=1);

namespace App\Services\Cache;

/**
 * Class CacheRecordParams
 * Handles all cache record params operations.
 *
 * @category  Services
 * @package   App\Services\Cache
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-15
 */
final readonly class CacheRecordParams
{
    /**
     * Constructor to initialize cache record parameters.
     *
     * @param string       $entity The entity name (e.g., table name).
     * @param string       $column The column name used for caching.
     * @param int|string   $value  The value of the column for caching.
     * @param mixed        $data   The data to be cached.
     * @param int|null     $ttl    Time to live in seconds (null for default).
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        public string $entity,
        public string $column,
        public int|string $value,
        public mixed $data,
        public ?int $ttl = null
    ) {
        // ttl in seconds, null means use default TTL
    }

    /**
     * Create instance from array (useful for dynamic data).
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entity: (string)($data['entity']),
            column: (string)($data['column']),
            value: $data['value'],
            data: $data['data'] ?? null,
            ttl: isset($data['ttl']) ? (int)$data['ttl'] : null
        );
    }
}
