<?php

/**
 * src/Repositories/Repository.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Repositories/Repository.php
 */
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Messages;
use App\Core\Pools\PDOPool;
use App\Services\PaginationParams;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Repository for managing records in the database.
 * This class provides CRUD operations for the 'records' table using PDO.
 *
 * @category  Repositories
 * @package   App\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
abstract readonly class Repository implements RepositoryInterface
{
    public const TAG = 'Repository';

    /**
     * Constructor to initialize the repository with a database context.
     *
     * @param PDOPool $pdoPool The database context for managing connections.
     */
    public function __construct(protected PDOPool $pdoPool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Binds dynamic query parameters.
     *
     * @param array<string, mixed> $params
     */
    protected function bindQueryParams(PDOStatement $pdoStatement, array $params): void
    {
        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $pdoStatement->bindValue(':' . $key, $val, $type);
        }
    }

    /**
     * Binds pagination offset and limit.
     */
    protected function bindPaginationParams(PDOStatement $pdoStatement, PaginationParams $paginationParams): void
    {
        $pdoStatement->bindValue(':offset', $paginationParams->offset, PDO::PARAM_INT);
        $pdoStatement->bindValue(':limit', $paginationParams->limit, PDO::PARAM_INT);
    }

    /**
     * Log the start of an filterCount.
     *
     * @param int   $pdoId ID of the PDO connection
     * @param PaginationParams $paginationParams  Data being used for the list
     */
    protected function logListStart(int $pdoId, PaginationParams $paginationParams): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'LIST',
            sprintf(
                'pdoId: %d Listing records with limit: %s, offset: %s, filters: %s, sortBy: %s, sortDir: %s',
                $pdoId,
                var_export($paginationParams->limit, true),
                var_export($paginationParams->offset, true),
                var_export($paginationParams->filters, true),
                var_export($paginationParams->sortBy, true),
                var_export($paginationParams->sortDir, true)
            )
        );
    }

    /**
     * Log the start of an filterCount.
     *
     * @param int   $pdoId ID of the PDO connection
     * @param array<string, mixed> $filters  Data being used for the filterCount
     */
    protected function logFilterCountStart(int $pdoId, array $filters): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT',
            sprintf('pdoId: %d Counting records with filters: %s', $pdoId, var_export($filters, true))
        );
    }

    /**
     * Log the start of an update.
     *
     * @param int   $pdoId ID of the PDO connection
     * @param int   $id    Record ID being updated
     * @param array<string, mixed> $data  Data being used for the update
     */
    protected function logUpdateStart(int $pdoId, int $id, array $data): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'UPDATE',
            sprintf('pdoId: %d Updating record ID: %d with data: %s', $pdoId, $id, var_export($data, true))
        );
    }

    /**
     * Log an exception during update.
     *
     * @param string    $functionName Name of the function (for logging)
     * @param int       $pdoId        ID of the PDO connection
     * @param Throwable $throwable    The caught exception
     */
    protected function logException(string $functionName, int $pdoId, Throwable $throwable): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . '' . $functionName . '][Exception',
            sprintf(
                Messages::PDO_EXCEPTION_MESSAGE,
                $pdoId,
                $throwable->getCode(),
                $throwable->getMessage()
            )
        );
    }

    /**
     * Clean up and log PDO statement info.
     *
     * @param string         $functionName Name of the function (for logging)
     * @param int            $pdoId        ID of the PDO connection
     * @param \PDOStatement|null $pdoStatement The PDO statement to finalize
     */
    protected function finalizeStatement(string $functionName, int $pdoId, ?\PDOStatement $pdoStatement): void
    {
        if (!$pdoStatement instanceof \PDOStatement) {
            return;
        }

        $errorCode = $pdoStatement->errorCode();
        $errorInfo = $pdoStatement->errorInfo();

        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . $functionName,
            sprintf(
                Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                $pdoId,
                $errorCode ?? 'N/A',
                implode(', ', $errorInfo)
            )
        );

        $this->pdoPool->clearStatement($pdoStatement);
    }
}
