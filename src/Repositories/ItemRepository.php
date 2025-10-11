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
use App\Core\Pools\PDOPool;
use App\Exceptions\CreateFailedException;
use App\Exceptions\ResourceNotFoundException;
use InvalidArgumentException;
use PDO;
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
 * @since     2025-10-02
 */
final readonly class ItemRepository
{
    /**
     * Constructor to initialize the repository with a database context.
     *
     * @param PDOPool $pdoPool The database context for managing connections.
     */
    public function __construct(private PDOPool $pdoPool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Create a new item in the database.
     *
     * @param array<int, mixed> $data Item data ('sku', 'title', 'price')
     *
     * @throws CreateFailedException If the insert operation fails.
     *
     * @return int The ID of the newly created item.
     */
    public function create(array $data): int
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($data): int {
            try {
                // Prepare INSERT statement with named parameters
                $stmt = $pdo->prepare('INSERT INTO items (sku, title, price) VALUES (:sku, :title, :price)');

                // Bind values safely
                $stmt->bindValue(':sku', $data['sku'], PDO::PARAM_STR);
                $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
                $stmt->bindValue(':price', isset($data['price']) ? (float)$data['price'] : null, PDO::PARAM_STR);
                $stmt->execute();

                $lastInsertId = $pdo->lastInsertId();
                // ✅ Fallback if returned 0 or empty
                if (!$lastInsertId || $lastInsertId === '0') {
                    $result       = $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
                    $lastInsertId = $result ?: false;
                }

                error_log('Last Insert ID: ' . var_export($lastInsertId, true));
                if ($lastInsertId === false || $lastInsertId === null || $lastInsertId === '' || $lastInsertId === '0') {
                    $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                    throw new CreateFailedException(Messages::CREATE_FAILED, 500);
                }

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return (int)$lastInsertId;
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }

    /**
     * Find an item by ID.
     *
     * @param int $id Item ID
     *
     * @return array|null Item data or null if not found
     */
    public function find(int $id): ?array
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id) {
            try {
                $stmt = $pdo->prepare('SELECT id, sku, title, price, created_at, updated_at FROM items WHERE id=:id LIMIT 1');

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                // Execute the prepared statement
                $stmt->execute();

                // Fetch all results as associative arrays
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                $result = $results[0] ?? null;
                if ($result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'Item id#' . $id), 404);
                }

                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }

    /**
     * Find an item by SKU.
     *
     * @param string $sku Item SKU
     *
     * @return array|null Item data or null if not found
     */
    public function findBySku(string $sku): ?array
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($sku) {
            try {
                $stmt = $pdo->prepare('SELECT id, sku, title, price, created_at, updated_at FROM items WHERE sku=:sku LIMIT 1');

                $stmt->bindValue(':sku', $sku, PDO::PARAM_STR);
                // Execute the prepared statement
                $stmt->execute();

                // Fetch all results as associative arrays
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                $result = $results[0] ?? null;
                if ($result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'Item sku#' . $sku), 404);
                }

                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }

    /**
     * List items with optional filters, sorting, and pagination.
     *
     * @param int               $limit   Max rows
     * @param int               $offset  Offset
     * @param array<int, mixed> $filters Filter conditions
     * @param string            $sortBy  Sort column
     * @param string            $sortDir Sort direction
     *
     * @return array List of items
     */
    public function list(
        int $limit = 20,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        return $this->pdoPool->withConnection(function (
            PDO $pdo,
            int $pdoId
        ) use (
            $limit,
            $offset,
            $filters,
            $sortBy,
            $sortDir
        ) {
            try {
                $limit  = max(1, min($limit, 100));
                $offset = max(0, $offset);

                $sql    = 'SELECT id, sku, title, price, created_at, updated_at FROM items';
                $where  = [];
                $params = [];

                // Apply filters
                foreach ($filters as $field => $value) {
                    if ($value === null) {
                        continue;
                    }

                    switch ($field) {
                        case 'sku':
                            $where[]       = 'sku = :sku';
                            $params['sku'] = $value;
                            break;
                        case 'title':
                            $where[]         = 'title LIKE :title';
                            $params['title'] = sprintf(' %%%s%%', $value);
                            break;
                        case 'created_after':
                            $where[]                 = 'created_at > :created_after';
                            $params['created_after'] = $value;
                            break;
                        case 'created_before':
                            $where[]                  = 'created_at < :created_before';
                            $params['created_before'] = $value;
                            break;
                        default:
                            throw new InvalidArgumentException('Invalid filter ' . $field);
                    }
                }

                if ($where !== []) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }

                // Sorting
                $allowedSort = ['id', 'sku', 'title', 'price', 'created_at', 'updated_at'];
                if (!in_array($sortBy, $allowedSort, true)) {
                    $sortBy = 'id';
                }

                $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
                $sql .= sprintf('  ORDER BY %s %s', $sortBy, $sortDir);

                // Pagination
                $sql .= ' LIMIT :offset, :limit';

                $stmt = $pdo->prepare($sql);

                // Bind filter parameters
                foreach ($params as $key => $val) {
                    $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue(':' . $key, $val, $type);
                }

                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $results;
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }

    /**
     * Count items with optional filters.
     *
     * @param array<int, mixed> $filters Filter conditions
     *
     * @return int Number of filtered items
     */
    public function filteredCount(array $filters = []): int
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($filters): int {
            try {
                $sql    = 'SELECT count(*) as filtered FROM items';
                $where  = [];
                $params = [];

                foreach ($filters as $field => $value) {
                    if ($value === null) {
                        continue;
                    }

                    switch ($field) {
                        case 'sku':
                            $where[]       = 'sku = :sku';
                            $params['sku'] = $value;
                            break;
                        case 'title':
                            $where[]         = 'title LIKE :title';
                            $params['title'] = sprintf(' %%%s%%', $value);
                            break;
                        case 'created_after':
                            $where[]                 = 'created_at > :created_after';
                            $params['created_after'] = $value;
                            break;
                        case 'created_before':
                            $where[]                  = 'created_at < :created_before';
                            $params['created_before'] = $value;
                            break;
                        default:
                            throw new InvalidArgumentException('Invalid filter ' . $field);
                    }
                }

                if ($where !== []) {
                    $sql .= ' WHERE ' . implode(' AND ', $where);
                }

                $stmt = $pdo->prepare($sql);

                foreach ($params as $key => $val) {
                    $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue(':' . $key, $val, $type);
                }

                // Execute the prepared statement
                $stmt->execute();

                // Fetch all results as associative arrays
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                $result = $results[0] ?? null;
                if ($result === null) {
                    return 0; // No rows returned
                }

                // Safely access the 'filtered' key
                return (int) ($result['filtered'] ?? 0);
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }

            return 0;
        });
    }

    /**
     * Count all items in the table.
     */
    public function count(): int
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId): int {
            try {
                $stmt = $pdo->prepare('SELECT count(*) as total FROM items');

                // Execute the prepared statement
                $stmt->execute();

                // Fetch all results as associative arrays
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                $result = $results[0] ?? null;
                if ($result === null) {
                    return 0; // No rows returned
                }

                // Safely access the 'total' key
                return (int) ($result['total'] ?? 0);
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }

    /**
     * Update an existing item.
     *
     * @param int               $id   Item ID
     * @param array<int, mixed> $data Item data ('sku', 'title', 'price')
     *
     * @return bool True if updated
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id, $data): bool {
            try {
                $stmt = $pdo->prepare('UPDATE items SET sku=:sku, title=:title, price=:price WHERE id=:id');

                $stmt->bindValue(':sku', $data['sku'], PDO::PARAM_STR);
                $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
                $stmt->bindValue(':price', (float)$data['price'], PDO::PARAM_STR);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);

                $stmt->execute();
                $result = $stmt->rowCount() > 0;
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }

    /**
     * Delete an item by ID.
     *
     * @param int $id Item ID
     *
     * @return bool True if deleted
     */
    public function delete(int $id): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id): bool {
            try {
                $stmt = $pdo->prepare('DELETE FROM items WHERE id=:id');

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                $result = $stmt->rowCount() > 0;
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $throwable->getCode(),
                        $throwable->getMessage()
                    )
                );
                throw $throwable;
            } finally {
                // Log PDO error code if $stmt exists
                $errorCode = $stmt?->errorCode(); // returns '00000' if no error
                $errorInfo = $stmt?->errorInfo(); // optional: [SQLSTATE, driverCode, message]

                // Log only if there was an error
                error_log(
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
                        self::class,
                        __LINE__,
                        $pdoId,
                        $errorCode ?? 'N/A',
                        $errorInfo ? implode(', ', $errorInfo) : 'N/A'
                    )
                );

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
            }
        });
    }
}
