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
use App\Exceptions\CreateFailedException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\StatementException;
use App\Models\User;
use App\Services\PaginationParams;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Repository for managing users in the database.
 * This class provides CRUD operations for the 'users' table using PDO.
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
     * Create a new user in the database.
     *
     * @param array<string, mixed> $data User data ('email', 'name')
     *
     * @throws CreateFailedException If the insert operation fails.
     *
     * @return int The ID of the newly created user.
     */
    public function create(array $data): int
    {
        return $this->pdoPool->withConnectionAndRetryForCreate('users', function (PDO $pdo, int $pdoId) use ($data): int {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' Creating user with data: ' . var_export($data, true));
                // Prepare INSERT statement with named parameters
                $sql  = 'INSERT INTO users (email, name) VALUES (:email, :name)';
                $stmt = $pdo->prepare($sql);

                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'CREATE', 'pdoId: ' . $pdoId . ' data: ' . var_export($data, true));

                // Bind values safely
                $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
                $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);

                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // ✅ Dump actual SQL and result
                $this->logSql('CREATE', $stmt, $data, $isExecuted);

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
     * Find an user by ID.
     *
     * @param int $id User ID
     *
     * @return User User data
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function find(int $id): User
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($id): User {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND', 'pdoId: ' . $pdoId . ' Finding user with ID: ' . var_export($id, true));
                $sql  = 'SELECT id, email, name, created_at, updated_at FROM users WHERE id=:id LIMIT 1';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                // Execute the prepared statement
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all results as associative arrays
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('FIND', $stmt, ['id' => $id], $result);

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                if ($result === false || $result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'User id#' . $id), 404);
                }

                return User::fromArray($result);
            } catch (Throwable $throwable) {
                $this->logException('FIND', $pdoId, $throwable);
                throw $throwable;
            } finally {
                $this->finalizeStatement('FIND', $pdoId, $stmt ?? null);
            }
        });
    }

    /**
     * Find an user by EMAIL.
     *
     * @param string $email User EMAIL
     *
     * @return User User data
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function findByEmail(string $email): User
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($email): User {
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'FIND_BY_EMAIL', 'pdoId: ' . $pdoId . ' Finding user with EMAIL: ' . var_export($email, true));
                $sql  = 'SELECT id, email, name, created_at, updated_at FROM users WHERE email=:email LIMIT 1';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                // Execute the prepared statement
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all result as associative array
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('FIND_BY_EMAIL', $stmt, ['email' => $email], $result);

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Return the first result if available
                if ($result === false || $result === null) {
                    throw new ResourceNotFoundException(sprintf(Messages::RESOURCE_NOT_FOUND, 'User email#' . $email), 404);
                }

                return User::fromArray($result);
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
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function list(PaginationParams $paginationParams): array
    {
        return $this->pdoPool->withConnectionAndRetry(function (PDO $pdo, int $pdoId) use ($paginationParams): array {
            $this->logListStart($pdoId, $paginationParams);
            try {
                $stmt = $this->prepareListStatement($pdo, $paginationParams);

                // Execute the prepared statement
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('LIST', $stmt, [], $results);

                $this->pdoPool->clearStatement($stmt); // ✅ mandatory for unbuffered or pooled Swoole

                // Hydrate each row into an User domain entity
                return array_map(fn (array $result): User => User::fromArray($result), $results);
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
            'email'          => static fn (string $v): array => ['email = :email', ['email' => $v]],
            'name'           => static fn (string $v): array => ['name LIKE :name', ['name' => sprintf('%%%s%%', $v)]],
            'created_after'  => static fn (string $v): array => ['created_at > :created_after', ['created_after' => $v]],
            'created_before' => static fn (string $v): array => ['created_at < :created_before', ['created_before' => $v]],
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
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all result as associative array
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('FILTERED_COUNT', $stmt, [], $result);

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
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // Fetch all result as associative array
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                // ✅ Dump actual SQL and result
                $this->logSql('COUNT', $stmt, [], $result);

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
                $stmt       = $this->prepareUpdateStatement($pdo, $id, $data);
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // ✅ Dump actual SQL and result
                $this->logSql('UPDATE', $stmt, $data, $isExecuted);

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
            try {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . 'DELETE', 'pdoId: ' . $pdoId . ' Deleting user with ID: ' . var_export($id, true));
                $sql  = 'DELETE FROM users WHERE id=:id';
                $stmt = $pdo->prepare($sql);

                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $isExecuted = $stmt->execute();
                if ($isExecuted === false) {
                    throw new StatementException(Messages::QUERY_FAILED, 500);
                }

                // ✅ Dump actual SQL and result
                $this->logSql('DELETE', $stmt, ['id' => $id], $isExecuted);

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
