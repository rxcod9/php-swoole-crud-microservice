<?php

/**
 * src/Services/PaginationTrait.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Services
 * @package   App\Services
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-14
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Services/PaginationTrait.php
 */
declare(strict_types=1);

namespace App\Services;

use App\Models\ModelInterface;
use App\Repositories\Repository;

/**
 * Trait to provide reusable pagination logic.
 *
 * @category  Services
 * @package   App\Services
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-14
 */
trait PaginationTrait
{
    abstract protected function getRepository(): Repository;

    /**
     * List records with optional filters, sorting, and pagination.
     *
     * @param PaginationParams $paginationParams Pagination parameters
     *
     * @return array{0: array<int, mixed>, 1: array<string, mixed>} Records + metadata
     */
    public function paginate(PaginationParams $paginationParams): array
    {
        $repo = $this->getRepository();

        $total         = $repo->count();
        $filteredTotal = $total === 0 ? 0 : $repo->filteredCount($paginationParams->filters);

        if ($filteredTotal === 0) {
            return [[], $this->buildPaginationMetadata($total, 0, $paginationParams->limit, $paginationParams->offset)];
        }

        $records = $repo->list($paginationParams);

        $metadata = $this->buildPaginationMetadata($total, $filteredTotal, $paginationParams->limit, $paginationParams->offset);

        // Map domain entities into serializable arrays
        $recordsArray = array_map(static fn (ModelInterface $model): array => $model->toArray(), $records);

        return [$recordsArray, $metadata];
    }

    /**
     * Build pagination metadata.
     *
     * @param int $total         Total records
     * @param int $filteredTotal Filtered count
     * @param int $limit         Records per page
     * @param int $offset        Offset
     *
     * @return array<string, mixed>
     */
    private function buildPaginationMetadata(int $total, int $filteredTotal, int $limit, int $offset): array
    {
        $pages = (int) ceil($total / $limit);

        return [
            'count'          => $filteredTotal === 0 ? 0 : min($limit, $filteredTotal),
            'current_page'   => (int) floor($offset / $limit) + 1,
            'filtered_total' => $filteredTotal,
            'per_page'       => $limit,
            'total_pages'    => $pages,
            'total'          => $total,
        ];
    }
}
