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

use App\Core\Constants;
use App\Core\Messages;
use App\Core\Pools\PDOPool;
use App\Services\PaginationParams;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Abstract repository for managing database records.
 * Provides common helpers for CRUD operations, pagination,
 * parameter binding, exception handling, and SQL logging.
 * All concrete repositories should extend this class and
 * implement specific table/entity logic.
 *
 * @category  Repositories
 * @package   App\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-26
 */
abstract readonly class Repository implements RepositoryInterface
{
    public const TAG = 'Repository';

    /**
     * Constructor to initialize the repository with a PDO connection pool.
     *
     * @param PDOPool $pdoPool The PDO connection pool instance.
     */
    public function __construct(protected PDOPool $pdoPool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Bind dynamic query parameters to a PDO statement.
     *
     * @param PDOStatement $pdoStatement The prepared PDO statement.
     * @param array<string, mixed> $params Associative array of parameters.
     */
    protected function bindQueryParams(PDOStatement $pdoStatement, array $params): void
    {
        foreach ($params as $key => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $pdoStatement->bindValue(':' . $key, $val, $type);
        }
    }

    /**
     * Bind pagination parameters (offset & limit) to a PDO statement.
     *
     * @param PDOStatement $pdoStatement The prepared PDO statement.
     * @param PaginationParams $paginationParams The pagination parameters.
     */
    protected function bindPaginationParams(PDOStatement $pdoStatement, PaginationParams $paginationParams): void
    {
        $pdoStatement->bindValue(':offset', $paginationParams->offset, PDO::PARAM_INT);
        $pdoStatement->bindValue(':limit', $paginationParams->limit, PDO::PARAM_INT);
    }

    /**
     * Log the start of a record list operation.
     *
     * @param int $pdoId PDO connection ID.
     * @param PaginationParams $paginationParams Pagination/filtering parameters.
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
     * Log the start of a filtered count operation.
     *
     * @param int $pdoId PDO connection ID.
     * @param array<string, mixed> $filters Filter parameters.
     */
    protected function logFilterCountStart(int $pdoId, array $filters): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT',
            sprintf('pdoId: %d Counting records with filters: %s', $pdoId, var_export($filters, true))
        );
    }

    /**
     * Log the start of an update operation.
     *
     * @param int $pdoId PDO connection ID.
     * @param int $id Record ID being updated.
     * @param array<string, mixed> $data Update data.
     */
    protected function logUpdateStart(int $pdoId, int $id, array $data): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'UPDATE',
            sprintf('pdoId: %d Updating record ID: %d with data: %s', $pdoId, $id, var_export($data, true))
        );
    }

    /**
     * Log PDO exceptions consistently.
     *
     * @param string $functionName Name of the function (for logging context).
     * @param int $pdoId PDO connection ID.
     * @param Throwable $throwable The exception thrown.
     */
    protected function logException(string $functionName, int $pdoId, Throwable $throwable): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . $functionName . '][Exception',
            sprintf(
                Messages::PDO_EXCEPTION_MESSAGE,
                $pdoId,
                $throwable->getCode(),
                $throwable->getMessage()
            )
        );
    }

    /**
     * Clean up and log PDO statement info after execution.
     *
     * @param string $functionName Function name for logging.
     * @param int $pdoId PDO connection ID.
     * @param PDOStatement|null $pdoStatement The statement to finalize.
     */
    protected function finalizeStatement(string $functionName, int $pdoId, ?PDOStatement $pdoStatement): void
    {
        if (!$pdoStatement instanceof PDOStatement) {
            return;
        }

        $errorCode = $pdoStatement->errorCode();
        $errorInfo = $pdoStatement->errorInfo();

        if ($errorCode !== null && $errorCode !== Constants::PDO_NO_ERROR_CODE) {
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . $functionName,
                sprintf(
                    Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                    $pdoId,
                    $errorCode,
                    implode(', ', $errorInfo)
                )
            );
        }

        $this->pdoPool->clearStatement($pdoStatement);
    }

    /**
     * Log SQL queries and execution results with parameter substitution.
     *
     * @param string $operation Operation name (CREATE, UPDATE, etc.).
     * @param PDOStatement $pdoStatement The PDO statement.
     * @param array<string, mixed> $params Bound query parameters.
     * @param float $queryTime Execution result.
     * @param mixed $result Execution result (optional).
     */
    protected function logSql(
        string $operation,
        PDOStatement $pdoStatement,
        array $params,
        float $queryTime,
        mixed $result = null
    ): void {
        if ((bool)(env('APP_DEBUG', false)) === false) {
            return;
        }

        $sqlWithValues = $pdoStatement->queryString ?? '';

        foreach ($params as $key => $val) {
            $val         = is_null($val) ? 'NULL' : $val;
            $replacement = is_string($val)
                ? "'" . addslashes($val) . "'"
                : $val;
            $sqlWithValues = preg_replace('/:' . preg_quote($key, '/') . '\b/', (string)$replacement, $sqlWithValues);
        }

        logDebug(static::TAG ?? __CLASS__, sprintf('[SQL] [%s] => Query time: %f ms %s', $operation, $queryTime, $sqlWithValues));

        if ($result !== null) {
            logDebug(static::TAG ?? __CLASS__, sprintf('[SQL-RESULT] [%s] => %s', $operation, var_export($result, true)));
        }

        if ($pdoStatement->errorCode() !== Constants::PDO_NO_ERROR_CODE) {
            $errorInfo = $pdoStatement->errorInfo();
            logDebug(static::TAG ?? __CLASS__, sprintf('[SQL-STATE] [%s] => %s', $operation, implode(', ', $errorInfo)));
        }
    }
}
