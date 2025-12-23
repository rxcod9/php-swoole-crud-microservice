<?php

/**
 * src/Services/PaginationParams.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Services
 * @package   App\Services
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-14
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/PaginationParams.php
 */
declare(strict_types=1);

namespace App\Services;

/**
 * Data Transfer Object for pagination parameters.
 *
 * @category  Services
 * @package   App\Services
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-14
 */
final readonly class PaginationParams
{
    /**
     * Constructor to initialize pagination parameters.
     *
     * @param int $limit Number of records per page.
     * @param int $offset Offset for pagination.
     * @param array<string,mixed> $filters Associative array of filters.
     * @param string $sortBy Column to sort by.
     * @param string $sortDir Sort direction ('asc' or 'desc').
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        public int $limit = 20,
        public int $offset = 0,
        public array $filters = [],
        public string $sortBy = 'id',
        public string $sortDir = 'DESC'
    ) {
        // sortDir can be 'asc' or 'desc'
    }

    /**
     * Create instance from array (useful for dynamic data).
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $limit  = isset($data['limit']) ? (int)$data['limit'] : 20;
        $offset = isset($data['offset']) ? (int)$data['offset'] : 0;

        return new self(
            // enforce bounds
            limit: max(1, min($limit, 100)),
            offset: max(0, $offset),
            filters: isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [],
            sortBy: isset($data['sortBy']) ? (string)$data['sortBy'] : 'id',
            sortDir: strtoupper($data['sortDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC'
        );
    }
}
