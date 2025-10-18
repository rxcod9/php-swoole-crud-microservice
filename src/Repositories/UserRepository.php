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
use App\Models\User;
use App\Services\PaginationParams;
use InvalidArgumentException;
use PDO;
use PDOStatement;
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
final readonly class UserRepository extends Repository
{
    public const TAG = 'UserRepository';

    /**
     * Constructor
     *
     * @param PDOPool $pdoPool The PDO connection pool
     */
    public function __construct(protected PDOPool $pdoPool)
    {
        // Initialize repository with PDO connection pool
    }

    /**
     * Create a new user in the database.
     *
     * @param array<string, mixed> $data User data ('email', 'name')
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
                $sql  = 'INSERT INTO users (email, name) VALUES (:email, :name)';
                $stmt = $pdo->prepare($sql);

                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' data: ' . var_export($data, true));

                // Bind values safely to prevent SQL injection
                $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
                $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);

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
                $this->logException('CREATE', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('CREATE', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Find a user by ID.
     *
     * @param int $id User ID
     *
     * @return array<string, mixed> User data if not found
     */
    public function find(int $id): array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($id) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND', 'pdoId: ' . $pdoId . ' Finding user with ID: ' . var_export($id, true));
            try {
                // Prepare SELECT query
                $sql  = 'SELECT id, email, name, created_at, updated_at FROM users WHERE id=:id LIMIT 1';
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
                $this->logException('FIND', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FIND', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Find a user by email.
     *
     * @param string $email Email address
     *
     * @return array<string, mixed> User data
     */
    public function findByEmail(string $email): array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($email) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_EMAIL', 'pdoId: ' . $pdoId . ' Finding user with email: ' . var_export($email, true));
            try {
                $sql  = 'SELECT id, email, name, created_at, updated_at FROM users WHERE email=:email LIMIT 1';
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
                $this->logException('FIND_BY_EMAIL', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FIND_BY_EMAIL', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * List users with optional filters, sorting, and pagination.
     *
     * @param PaginationParams $paginationParams Pagination parameters
     *
     * @return array<int, User> Array of users
     */
    public function list(PaginationParams $paginationParams): array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($paginationParams): array {
            $this->logListStart($pdoId, $paginationParams);
            try {
                $stmt = $this->prepareListStatement($pdo, $paginationParams);

                // Execute the prepared statement
                $stmt->execute();

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Hydrate each row into an User domain entity
                return array_map(static fn (array $result): \App\Models\User => User::fromArray($result), $results);
            } catch (Throwable $throwable) {
                $this->logException('LIST', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('LIST', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Builds and prepares a List query for users.
     */
    private function prepareListStatement(PDO $pdo, PaginationParams $paginationParams): PDOStatement
    {
        $sql    = 'SELECT id, email, name, created_at, updated_at FROM users';
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
    protected function buildWhereClause(array $filters, array &$params): string
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
     * @return array<string, callable(string): array{0:string,1:array<string, string>}>
     */
    private function getAllowedFilters(): array
    {
        return [
            'email'          => static fn (string $value): array => ['email = :email', ['email' => $value]],
            'name'           => static fn (string $value): array => ['name LIKE :name', ['name' => sprintf('%%%s%%', $value)]],
            'created_after'  => static fn (string $value): array => ['created_at > :created_after', ['created_after' => $value]],
            'created_before' => static fn (string $value): array => ['created_at < :created_before', ['created_before' => $value]],
        ];
    }

    /**
     * Builds ORDER BY clause.
     */
    private function buildOrderByClause(string $sortBy, string $sortDir): string
    {
        $allowedSort = ['id', 'email', 'name', 'created_at', 'updated_at'];
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
                'pdoId: %d Listing users with limit: %s, offset: %s, filters: %s, sortBy: %s, sortDir: %s',
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
     * Count filtered users.
     *
     * @param array<string, mixed> $filters Optional filters
     *
     * @return int Number of filtered users
     */
    public function filteredCount(array $filters = []): int
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($filters): int {
            $this->logFilterCountStart($pdoId, $filters);
            try {
                $stmt = $this->prepareFilterCountStatement($pdo, $filters);

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
                $this->logException('FILTERED_COUNT', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FILTERED_COUNT', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Builds and prepares a filtered COUNT(*) query for users.
     *
     * @param array<string, mixed> $filters
     */
    private function prepareFilterCountStatement(PDO $pdo, array $filters = []): PDOStatement
    {
        $sql    = 'SELECT COUNT(*) AS filtered FROM users';
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
     * Log the start of an filterCount.
     *
     * @param int   $pdoId ID of the PDO connection
     * @param array<string, mixed> $filters  Data being used for the filterCount
     */
    protected function logFilterCountStart(int $pdoId, array $filters): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'FILTERED_COUNT',
            sprintf('pdoId: %d Counting users with filters: %s', $pdoId, var_export($filters, true))
        );
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
                $this->logException('COUNT', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('COUNT', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Update a user record.
     *
     * @param int                 $id   User ID
     * @param array<string, mixed> $data User data ('email', 'name')
     *
     * @return bool True if updated
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdoPool->withConnection(function (PDO $pdo, int $pdoId) use ($id, $data): bool {
            $this->logUpdateStart($pdoId, $id, $data);

            try {
                $stmt = $this->prepareUpdateStatement($pdo, $id, $data);
                $stmt->execute();

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
     * Prepare the PDO statement for updating user.
     *
     * @param array<string, mixed> $data
     */
    private function prepareUpdateStatement(PDO $pdo, int $id, array $data): \PDOStatement
    {
        $sql  = 'UPDATE users SET email=:email, name=:name WHERE id=:id';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt;
    }

    /**
     * Log the start of an update.
     *
     * @param int   $pdoId ID of the PDO connection
     * @param int   $id    User ID being updated
     * @param array<string, mixed> $data  Data being used for the update
     */
    protected function logUpdateStart(int $pdoId, int $id, array $data): void
    {
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . 'UPDATE',
            sprintf('pdoId: %d Updating user ID: %d with data: %s', $pdoId, $id, var_export($data, true))
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
                $this->logException('DELETE', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('DELETE', $pdoId, $stmt ?? null);
            }
        });
    }
}
