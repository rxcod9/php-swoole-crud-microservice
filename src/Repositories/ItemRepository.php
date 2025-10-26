<?php

/**
 * src/Repositories/ItemRepository.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Repositories/ItemRepository.php
 */
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Messages;
use App\Exceptions\CreateFailedException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\StatementException;
use App\Models\Item;
use App\Services\PaginationParams;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Repository for managing items in the database.
 * This class provides CRUD operations for the 'items' table using PDO.
 *
 * @category  Repositories
 * @package   App\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-26
 */
final readonly class ItemRepository extends Repository
{
    public const TAG = 'ItemRepository';

    /**
     * Create a new item in the database.
     *
     * @param array<string, mixed> $data Item data ('sku', 'title', 'price')
     *
     * @throws CreateFailedException If the insert operation fails.
     *
     * @return int The ID of the newly created item.
     */
    public function create(array $data): int
    {
        return $this->pdoPool->withConnectionAndRetryForCreate('items', function (PDO $pdo, int $pdoId) use ($data): int {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' Creating item with data: ' . var_export($data, true));
                // Prepare INSERT statement with named parameters
                $sql  = 'INSERT INTO items (sku, title, price) VALUES (:sku, :title, :price)';
                $stmt = $pdo->prepare($sql);

                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' data: ' . var_export($data, true));

                // Bind values safely
                $stmt->bindValue(':sku', $data['sku'], PDO::PARAM_STR);
                $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
                $stmt->bindValue(':price', isset($data['price']) ? (float)$data['price'] : null, PDO::PARAM_STR);

                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // ✅ Dump actual SQL and result
                $this->logSql('CREATE', $stmt, $data, $queryTimeMs, $isExecuted);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                $lastInsertId = $pdo->lastInsertId();

                if (in_array($lastInsertId, [false, null, '', '0'], true)) {
                    $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                    throw new CreateFailedException(Messages::CREATE_FAILED, 500);
                }

                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' Last Insert ID: ' . var_export($lastInsertId, true));

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return (int)$lastInsertId;
            } catch (Throwable $throwable) {
                $this->logException('CREATE', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('CREATE', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Find an item by ID.
     *
     * @param int $id Item ID
     *
     * @return Item Item data
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function find(int $id): Item
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($id): Item {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND', 'pdoId: ' . $pdoId . ' Finding item with ID: ' . var_export($id, true));
                $sql  = 'SELECT id, sku, title, price, created_at, updated_at FROM items WHERE id=:id LIMIT 1';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                // Execute the prepared statement
                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all results as associative arrays
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('FIND', $stmt, ['id' => $id], $queryTimeMs, $result);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                if ($result === false || $result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'Item id#' . $id), 404);
                }

                return Item::fromArray($result);
            } catch (Throwable $throwable) {
                $this->logException('FIND', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FIND', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Find an item by SKU.
     *
     * @param string $sku Item SKU
     *
     * @return Item Item data
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function findBySku(string $sku): Item
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($sku): Item {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_SKU', 'pdoId: ' . $pdoId . ' Finding item with SKU: ' . var_export($sku, true));
                $sql  = 'SELECT id, sku, title, price, created_at, updated_at FROM items WHERE sku=:sku LIMIT 1';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':sku', $sku, PDO::PARAM_STR);
                // Execute the prepared statement
                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all result as associative array
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('FIND_BY_SKU', $stmt, ['sku' => $sku], $queryTimeMs, $result);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_SKU_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                if ($result === false || $result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'Item sku#' . $sku), 404);
                }

                return Item::fromArray($result);
            } catch (Throwable $throwable) {
                $this->logException('FIND_BY_SKU', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FIND_BY_SKU', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * List items with optional filters, sorting, and pagination.
     *
     * @param PaginationParams $paginationParams Pagination parameters
     *
     * @return array<int, Item> Array of items
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function list(PaginationParams $paginationParams): array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($paginationParams): array {
            try {
                $this->logListStart($pdoId, $paginationParams);
                $stmt = $this->prepareListStatement($pdo, $paginationParams);

                // Execute the prepared statement
                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('LIST', $stmt, [], $queryTimeMs, $results);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'LIST_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Hydrate each row into an Item domain entity
                return array_map(fn (array $result): Item => Item::fromArray($result), $results);
            } catch (Throwable $throwable) {
                $this->logException('LIST', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('LIST', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Builds and prepares a List query for items.
     */
    private function prepareListStatement(PDO $pdo, PaginationParams $paginationParams): PDOStatement
    {
        $sql    = 'SELECT id, sku, title, price, created_at, updated_at FROM items';
        $params = [];

        $whereSql = $this->buildWhereClause($paginationParams->filters, $params);
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $sql .= $this->buildOrderByClause($paginationParams->sortBy, $paginationParams->sortDir);
        $sql .= ' LIMIT :offset, :limit';

        $stmt = $pdo->prepare($sql);

        $this->bindQueryParams($stmt, $params);
        $this->bindPaginationParams($stmt, $paginationParams);

        return $stmt;
    }

    /**
     * Builds WHERE clause dynamically from filters.
     *
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     */
    private function buildWhereClause(array $filters, array &$params): string
    {
        $conditions     = [];
        $allowedFilters = $this->getAllowedFilters();

        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            if (!isset($allowedFilters[$field])) {
                throw new InvalidArgumentException(sprintf('Invalid filter: %s', $field));
            }

            [$condition, $param] = $allowedFilters[$field]($value);
            $conditions[]        = $condition;
            $params += $param;
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Returns filter-to-SQL mapping closures.
     *
     * @return array<string, callable(string): array{0:string,1:array<string, mixed>}>
     */
    private function getAllowedFilters(): array
    {
        return [
            'sku'            => static fn (string $v): array => ['sku = :sku', ['sku' => $v]],
            'title'          => static fn (string $v): array => ['title LIKE :title', ['title' => sprintf('%%%s%%', $v)]],
            'price'          => static fn (string $v): array => ['price = :price', ['price' => $v]],
            'created_after'  => static fn (string $v): array => ['created_at > :created_after', ['created_after' => $v]],
            'created_before' => static fn (string $v): array => ['created_at < :created_before', ['created_before' => $v]],
        ];
    }

    /**
     * Builds ORDER BY clause.
     */
    private function buildOrderByClause(string $sortBy, string $sortDir): string
    {
        $allowedSort = ['id', 'sku', 'title', 'price', 'created_at', 'updated_at'];
        $sortBy      = in_array($sortBy, $allowedSort, true) ? $sortBy : 'id';
        $sortDir     = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        return sprintf(' ORDER BY %s %s', $sortBy, $sortDir);
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
     * Count filtered items.
     *
     * @param array<string, mixed> $filters Optional filters
     *
     * @return int Number of filtered items
     */
    public function filteredCount(array $filters = []): int
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($filters): int {
            try {
                $this->logFilterCountStart($pdoId, $filters);
                $stmt = $this->prepareFilterCountStatement($pdo, $filters);

                // Execute the prepared statement
                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all result as associative array
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('FILTERED_COUNT', $stmt, [], $queryTimeMs, $result);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                if ($result === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                if ($result === null) {
                    return 0; // No rows returned
                }

                // Safely access the 'filtered' key
                return (int) ($result['filtered'] ?? 0);
            } catch (Throwable $throwable) {
                $this->logException('FILTERED_COUNT', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FILTERED_COUNT', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Builds and prepares a filtered COUNT(*) query for items.
     *
     * @param array<string, mixed> $filters
     */
    private function prepareFilterCountStatement(PDO $pdo, array $filters = []): PDOStatement
    {
        $sql    = 'SELECT COUNT(*) AS filtered FROM items';
        $params = [];

        $whereSql = $this->buildWhereClause($filters, $params);
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $stmt = $pdo->prepare($sql);

        $this->bindQueryParams($stmt, $params);

        return $stmt;
    }

    /**
     * Count total items in the table.
     */
    public function count(): int
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId): int {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'COUNT', 'pdoId: ' . $pdoId . ' Counting all items');
                $sql  = 'SELECT count(*) as total FROM items';
                $stmt = $pdo->prepare($sql);

                // Execute the prepared statement
                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all result as associative array
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('COUNT', $stmt, [], $queryTimeMs, $result);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'COUNT_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                if ($result === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                if ($result === null) {
                    return 0; // No rows returned
                }

                // Safely access the 'total' key
                return (int) ($result['total'] ?? 0);
            } catch (Throwable $throwable) {
                $this->logException('COUNT', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('COUNT', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Update a item record.
     *
     * @param int                 $id   Item ID
     * @param array<string, mixed> $data Item data ('sku', 'title', 'price')
     *
     * @return bool True if updated
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id, $data): bool {
            try {
                $this->logUpdateStart($pdoId, $id, $data);
                $stmt = $this->prepareUpdateStatement($pdo, $id, $data);

                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // ✅ Dump actual SQL and result
                $this->logSql('UPDATE', $stmt, $data, $queryTimeMs, $isExecuted);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'UPDATE_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                $result = $stmt->rowCount() > 0;
                $this->pdoPool->clearStatement($stmt);
                return $result;
            } catch (Throwable $throwable) {
                $this->logException('UPDATE', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('UPDATE', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Prepare the PDO statement for updating item.
     *
     * @param array<string, mixed> $data
     */
    private function prepareUpdateStatement(PDO $pdo, int $id, array $data): \PDOStatement
    {
        $sql  = 'UPDATE items SET sku=:sku, title=:title, price=:price WHERE id=:id';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':sku', $data['sku'], PDO::PARAM_STR);
        $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $stmt->bindValue(':price', $data['price'], PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt;
    }

    /**
     * Delete a item by ID.
     *
     * @param int $id Item ID
     *
     * @return bool True if deleted
     */
    public function delete(int $id): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id): bool {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'DELETE', 'pdoId: ' . $pdoId . ' Deleting item with ID: ' . var_export($id, true));
                $sql  = 'DELETE FROM items WHERE id=:id';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);

                $queryStart  = microtime(true);
                $isExecuted  = $stmt->execute();
                $queryTimeMs = round((microtime(true) - $queryStart) * 1000, 3);

                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // ✅ Dump actual SQL and result
                $this->logSql('DELETE', $stmt, ['id' => $id], $queryTimeMs, $isExecuted);

                // Log query time
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'DELETE_QUERY_TIME', 'pdoId: ' . $pdoId . ' Query time: ' . $queryTimeMs . ' ms');

                $result = $stmt->rowCount() > 0;
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $result;
            } catch (Throwable $throwable) {
                $this->logException('DELETE', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('DELETE', $pdoId, $stmt ?? null);
            }
        });
    }
}
