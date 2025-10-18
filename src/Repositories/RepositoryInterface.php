<?php

/**
 * src/Repositories/RepositoryInterface.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Repositories
 * @package   App\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Repositories/RecordRepository.php
 */
declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\CreateFailedException;
use App\Models\Model;
use App\Services\PaginationParams;

/**
 * Interface RepositoryInterface
 *
 * @category  Repositories
 * @package   App\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
interface RepositoryInterface
{
    /**
     * Create a new record in the database.
     *
     * @param array<string, mixed> $data Record data ('sku', 'title', 'price')
     *
     * @throws CreateFailedException If the insert operation fails.
     *
     * @return int The ID of the newly created record.
     */
    public function create(array $data): int;

    /**
     * Find an record by ID.
     *
     * @param int $id Record ID
     *
     * @return array<string, mixed> Record data or null if not found
     */
    public function find(int $id): array;

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param PaginationParams $paginationParams Pagination parameters
     *
     * @return array<int, Model> Array of records
     */
    public function list(PaginationParams $paginationParams): array;

    /**
     * Count filtered records.
     *
     * @param array<string, mixed> $filters Optional filters
     *
     * @return int Number of filtered records
     */
    public function filteredCount(array $filters = []): int;

    /**
     * Count total records in the table.
     */
    public function count(): int;

    /**
     * Update a record record.
     *
     * @param int                 $id   Record ID
     * @param array<string, mixed> $data Record data ('sku', 'title', 'price')
     *
     * @return bool True if updated
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a record by ID.
     *
     * @param int $id Record ID
     *
     * @return bool True if deleted
     */
    public function delete(int $id): bool;
}
