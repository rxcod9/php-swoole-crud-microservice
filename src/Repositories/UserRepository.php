<?php

/**
 * src/Repositories/UserRepository.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Repositories/UserRepository.php
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
 * Class UserRepository
 * Repository for managing users in the database.
 * Provides CRUD operations: create, read, update, delete, and list users.
 *
 * @category  Repositories
 * @package   App\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class UserRepository
{
    public const TAG = 'UserRepository';

    /**
     * Constructor
     *
     * @param PDOPool $pdoPool The PDO connection pool
     */
    public function __construct(private PDOPool $pdoPool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Create a new user in the database.
     *
     * @param array<int, mixed> $data User data ('name', 'email')
     *
     * @throws CreateFailedException on failure
     *
     * @return int Last inserted user ID
     */
    public function create(array $data): int
    {
        return $this->pdoPool->withConnectionAndRetryForCreate(function (PDO $pdo, int $pdoId) use ($data): int {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' Creating user with data: ' . var_export($data, true));
            try {
                // Prepare INSERT statement with named parameters
                $sql  = 'INSERT INTO users (name, email) VALUES (:name, :email)';
                $stmt = $pdo->prepare($sql);

                // Bind values safely to prevent SQL injection
                $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
                $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);

                $stmt->execute();

                // Return ID of the newly created user
                $lastInsertId = $pdo->lastInsertId();

                if (in_array($lastInsertId, [false, null, '', '0'], true)) {
                    $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                    throw new CreateFailedException(Messages::CREATE_FAILED, 500);
                }

                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' Last Insert ID: ' . var_export($lastInsertId, true));

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return (int)$lastInsertId;
            } catch (Throwable $throwable) {
                // Log exception here
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'CREATE' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'CREATE',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * Find a user by ID.
     *
     * @param int $id User ID
     *
     * @return array|null User data or null if not found
     */
    public function find(int $id): ?array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($id) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND', 'pdoId: ' . $pdoId . ' Finding user with ID: ' . var_export($id, true));
            try {
                // Prepare SELECT query
                $sql  = 'SELECT id, name, email, created_at, updated_at FROM users WHERE id=:id LIMIT 1';
                $stmt = $pdo->prepare($sql);

                // Bind ID parameter
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                // Execute the prepared statement
                $stmt->execute();

                // Fetch all results as associative arrays
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                $result = $results[0] ?? null;
                if ($result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'User id#' . $id), 404);
                }

                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'FIND' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'FIND',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * Find a user by email.
     *
     * @param string $email Email address
     *
     * @return array|null User data or null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($email) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_EMAIL', 'pdoId: ' . $pdoId . ' Finding user with email: ' . var_export($email, true));
            try {
                $sql  = 'SELECT id, name, email, created_at, updated_at FROM users WHERE email=:email LIMIT 1';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                // Execute the prepared statement
                $stmt->execute();

                // Fetch all results as associative arrays
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                $result = $results[0] ?? null;
                if ($result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'User email#' . $email), 404);
                }

                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_EMAIL' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_EMAIL',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * List users with optional filters, sorting, and pagination.
     *
     * @param int               $limit   Max rows (default 100, max 1000)
     * @param int               $offset  Offset for pagination
     * @param array<int, mixed> $filters Associative array of filters
     * @param string            $sortBy  Column to sort by
     * @param string            $sortDir Sort direction ('ASC' or 'DESC')
     *
     * @return array Array of users
     */
    public function list(
        int $limit = 20,
        int $offset = 0,
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        return $this->pdoPool->withConnectionAndRetry(function (
            PDO $pdo,
            int $pdoId
        ) use (
            $limit,
            $offset,
            $filters,
            $sortBy,
            $sortDir
        ) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'LIST', 'pdoId: ' . $pdoId . ' Listing users with limit: ' . var_export($limit, true) . ', offset: ' . var_export($offset, true) . ', filters: ' . var_export($filters, true) . ', sortBy: ' . var_export($sortBy, true) . ', sortDir: ' . var_export($sortDir, true));
            try {
                // Validate pagination values
                $limit  = max(1, min($limit, 100));
                $offset = max(0, $offset);

                $sql    = 'SELECT id, name, email, created_at, updated_at FROM users';
                $where  = [];
                $params = [];

                // Build dynamic WHERE clause using filters
                foreach ($filters as $field => $value) {
                    if ($value === null) {
                        continue;
                    }

                    switch ($field) {
                        case 'email':
                            $where[]         = 'email = :email';
                            $params['email'] = $value;
                            break;
                        case 'name':
                            $where[]        = 'name LIKE :name';
                            $params['name'] = sprintf('%%%s%%', $value);
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

                // Validate sorting column
                $allowedSort = ['id', 'name', 'email', 'created_at', 'updated_at'];
                if (!in_array($sortBy, $allowedSort, true)) {
                    $sortBy = 'id';
                }

                $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
                $sql .= sprintf(' ORDER BY %s %s', $sortBy, $sortDir);

                // Add pagination parameters
                $sql .= ' LIMIT :offset, :limit';

                $stmt = $pdo->prepare($sql);

                // Bind filter parameters
                foreach ($params as $key => $val) {
                    $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
                    $stmt->bindValue(':' . $key, $val, $type);
                }

                // Bind limit and offset
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

                $stmt->execute();

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $results;
            } catch (Throwable $throwable) {
                // Log exception here
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'LIST' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'LIST',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * Count filtered users.
     *
     * @param array<int, mixed> $filters Optional filters
     *
     * @return int Number of filtered users
     */
    public function filteredCount(array $filters = []): int
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($filters): int {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT', 'pdoId: ' . $pdoId . ' Counting users with filters: ' . var_export($filters, true));
            try {
                $sql    = 'SELECT count(*) as filtered FROM users';
                $where  = [];
                $params = [];

                // Build dynamic WHERE clause using filters
                foreach ($filters as $field => $value) {
                    if ($value === null) {
                        continue;
                    }

                    switch ($field) {
                        case 'email':
                            $where[]         = 'email = :email';
                            $params['email'] = $value;
                            break;
                        case 'name':
                            $where[]        = 'name LIKE :name';
                            $params['name'] = sprintf('%%%s%%', $value);
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

                // Bind filter parameters
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * Count total users in the table.
     */
    public function count(): int
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId): int {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'COUNT', 'pdoId: ' . $pdoId . ' Counting all users');
            try {
                $sql  = 'SELECT count(*) as total FROM users';
                $stmt = $pdo->prepare($sql);

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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'COUNT' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'COUNT',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * Update a user.
     *
     * @param int               $id   User ID
     * @param array<int, mixed> $data User data ('name', 'email')
     *
     * @return bool True if updated
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id, $data): bool {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'UPDATE', 'pdoId: ' . $pdoId . ' Updating user with ID: ' . var_export($id, true) . ' and data: ' . var_export($data, true));
            try {
                $sql  = 'UPDATE users SET name=:name, email=:email WHERE id=:id';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
                $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);

                $stmt->execute();
                $result = $stmt->rowCount() > 0;
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'UPDATE' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'UPDATE',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
     * Delete a user by ID.
     *
     * @param int $id User ID
     *
     * @return bool True if deleted
     */
    public function delete(int $id): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id): bool {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'DELETE', 'pdoId: ' . $pdoId . ' Deleting user with ID: ' . var_export($id, true));
            try {
                $sql  = 'DELETE FROM users WHERE id=:id';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                $result = $stmt->rowCount() > 0;
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole
                return $result;
            } catch (Throwable $throwable) {
                // Log exception here
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'DELETE' . '][Exception',
                    sprintf(
                        Messages::PDO_EXCEPTION_MESSAGE,
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
                logDebug(
                    self::TAG . ':' . __LINE__ . '] [' . 'DELETE',
                    sprintf(
                        Messages::PDO_EXCEPTION_FINALLY_MESSAGE,
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
