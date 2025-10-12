<?php

/**
 * src/Core/Pools/PDOPool.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Pools
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Pools/PDOPool.php
 */
declare(strict_types=1);

namespace App\Core\Pools;

use App\Exceptions\ChannelException;
use App\Exceptions\PdoPoolExhaustedException;
use App\Exceptions\PdoPoolNotInitializedException;
use App\Traits\Retryable;
use PDO;
use PDOException;
use PDOStatement;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * Class PDOPool
 * Coroutine-safe PDO connection pool for MySQL.
 * Automatically scales pool size based on usage.
 *
 * @category  Core
 * @package   App\Core\Pools
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class PDOPool
{
    use Retryable;

    public const TAG = 'PDOPool';

    private readonly Channel $channel;

    private bool $initialized = false;

    private int $created = 0;

    /** @var array<int, PDO> Active PDO instances per coroutine */
    private array $active = [];

    /**
     * PDOPool constructor.
     *
     * @param array<int, mixed> $conf       MySQL connection config: host, port, user, pass, db, charset, timeout
     * @param int               $min        Minimum connections to pre-create
     * @param int               $max        Maximum connections allowed
     * @param float             $idleBuffer Idle buffer ratio (0-1)
     * @param float             $margin     Scaling margin (0-1)
     */
    public function __construct(
        private array $conf,
        private readonly int $min = 5,
        private readonly int $max = 50,
        private readonly float $idleBuffer = 0.025,
        private readonly float $margin = 0.025
    ) {
        $this->channel = new Channel($this->max);
    }

    /**
     * Initialize pool (typically called from worker start).
     *
     * @param int $maxRetry Max retries for creating initial connections
     */
    public function init(int $maxRetry = 5): void
    {
        // Pre-create minimum connections to avoid startup delays
        for ($i = 0; $i < $this->min; ++$i) {
            $success = $this->channel->push($this->make(0, $maxRetry, 100));
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Pre-created PDO connection #%d', $i + 1));
            if ($success === false) {
                throw new ChannelException('Unable to push to channel' . PHP_EOL);
            }
        }

        $this->initialized = true;
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDOPool initialized with min=%d, max=%d', $this->min, $this->max));
    }

    /**
     * Create a new PDO connection.
     *
     * @param int $attempt Current retry attempt count.
     *
     * @throws RuntimeException
     */
    /**
     * Create a new PDO connection.
     * @param int $attempt Current retry attempt count.
     * @throws RuntimeException
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function make(int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): array
    {
        return $this->retry(function (): array {
            // Build DSN and create PDO
            $dsn = sprintf(
                $this->conf['dsn'] ?? 'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->conf['host'] ?? '127.0.0.1',
                $this->conf['port'] ?? 3306,
                $this->conf['db'] ?? 'app',
                $this->conf['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO(
                $dsn,
                $this->conf['user'] ?? 'root',
                $this->conf['pass'] ?? '',
                [
                    PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT               => false, // we manage pool manually
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]
            );
            $pdoIdStmt    = $pdo->query('SELECT CONNECTION_ID() AS id');
            $connectionId = (int) $pdoIdStmt->fetchColumn();
            ++$this->created;
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO connection created. Total connections: %d', $this->created));
            return [$pdo, $connectionId];
        }, $attempt, $maxRetry, $delayMs);
    }

    /**
     * @param float $timeout seconds to wait for a connection
     *
     * @return array{0: PDO, 1: int}
     *
     * @throws PdoPoolNotInitializedException
     * @throws PdoPoolExhaustedException
     */
    public function get(float $timeout = 1.0): array
    {
        if (!$this->initialized) {
            throw new PdoPoolNotInitializedException('PDO pool not initialized');
        }

        $available = $this->channel->length();
        $used      = $this->created - $available;

        // Auto-scale up
        if (($available <= 1) && $this->created < $this->max) {
            $toCreate = 1;
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[SCALE-UP PDO] Creating %d new connections (used: %d, available: %d)', $toCreate, $used, $available));

            [$pdo, $pdoId] = $this->make();
            return [$pdo, $pdoId];
        }

        $item = $this->channel->pop($timeout);
        if (!$item) {
            throw new PdoPoolExhaustedException('PDO pool exhausted', 503);
        }

        [$pdo, $pdoId] = $item;

        if (!$this->isConnected($pdo)) {
            unset($pdo);
            $this->created = max(0, $this->created - 1);
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO dead connection destroyed. Total connections: %d', $this->created));
            // create a fresh connection synchronously (preserve previous semantics)

            [$pdo, $pdoId] = $this->make();
            return [$pdo, $pdoId];
        }

        return [$pdo, $pdoId];
    }

    /**
     * Lightweight connectivity check. Returns true when a simple query succeeds.
     */
    public function isConnected(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchAll(\PDO::FETCH_ASSOC);
            // Clear any remaining result set to be safe in pool context
            $this->clearStatement($stmt);
            return true;
        } catch (PDOException $pdoException) {
            return false;
        }
    }

    /**
     * Ensure statement rows are consumed and cursor closed. Safe no-op when null.
     */
    public function clearStatement(?PDOStatement $pdoStatement): void
    {
        if (!$pdoStatement instanceof PDOStatement) {
            return;
        }

        // Consume any remaining rows (important for unbuffered queries)
        while ($pdoStatement->fetch(\PDO::FETCH_ASSOC)) {
            // discard rows
        }

        // Close cursor to free resources
        $pdoStatement->closeCursor();
    }

    /**
     * Return a PDO connection to the pool.
     * If the pool is full or the PDO is dead, the PDO is discarded.
     *
     * @throws ChannelException
     */
    public function put(PDO $pdo, int $pdoId): void
    {
        if (!$this->channel->isFull() && $this->isConnected($pdo)) {
            $success = $this->channel->push([$pdo, $pdoId]);
            if ($success === false) {
                throw new ChannelException('Unable to push to channel' . PHP_EOL);
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'PDO connection returned to pool');
            return;
        }

        // Pool full, let garbage collector close the PDO object
        unset($pdo);
        $this->created = max(0, $this->created - 1);
        if ($this->channel->isFull()) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Pool full, PDO connection discarded. Total connections: %d', $this->created));
        } else {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO dead connection destroyed. Total connections: %d', $this->created));
        }
    }

    /**
     * Execute a callback within a pooled PDO connection.
     * Re-entrant safe: nested calls reuse the same PDO for the current coroutine.
     *
     * @template T
     * @param callable(PDO,int):T $callback
     * @return T
     *
     * @throws Throwable
     */
    public function withConnection(callable $callback): mixed
    {
        $cid = Coroutine::getCid();

        $outermost = false;

        if (!isset($this->active[$cid])) {
            // Outermost call for this coroutine
            [$pdo, $pdoId]      = $this->get();
            $this->active[$cid] = [$pdo, $pdoId];
            $outermost          = true;
        } else {
            // Nested call, reuse the same PDO
            [$pdo, $pdoId] = $this->active[$cid];
            // check if connection is still alive
            if (!$this->isConnected($pdo)) {
                // Connection is dead, remove from active and get a new one
                unset($this->active[$cid]);
                $this->created = max(0, $this->created - 1);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO connection destroyed. Total connections: %d', $this->created));
                [$pdo, $pdoId]      = $this->get();
                $this->active[$cid] = [$pdo, $pdoId];
            }
        }

        try {
            return $callback($pdo, $pdoId);
        } finally {
            if ($outermost) {
                [$pdo, $pdoId] = $this->active[$cid] ?? [null, null];
                unset($this->active[$cid]);
                if ($pdo !== null && $pdoId !== null) {
                    $this->put($pdo, $pdoId); // Return connection to pool
                }
            }
        }
    }

    /**
     * Execute a callback within a pooled PDO connection.
     * Re-entrant safe: nested calls reuse the same PDO for the current coroutine.
     *
     * @template T
     * @param callable(PDO,int):T $callback
     * @return T
     *
     * @throws Throwable
     */
    public function withConnectionAndRetry(callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        return $this->withConnection(function (PDO $pdo, int $pdoId) use ($callback, $attempt, $maxRetry, $delayMs): mixed {
            return $this->retryConnection($pdoId, function () use ($callback, $pdo, $pdoId): mixed {
                return $callback($pdo, $pdoId);
            }, $attempt, $maxRetry, $delayMs);
        });
    }

    /**
     * Execute a callback within a pooled PDO connection.
     * Re-entrant safe: nested calls reuse the same PDO for the current coroutine.
     *
     * @template T
     * @param callable(PDO,int):T $callback
     * @return int (primary key of created)
     *
     * @throws Throwable
     */
    public function withConnectionAndRetryForCreate(callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100, ?callable $onDuplicateCallback = null): int
    {
        return $this->withConnection(function (PDO $pdo, int $pdoId) use ($callback, $attempt, $maxRetry, $delayMs, $onDuplicateCallback): int {
            return $this->retryConnectionForCreate($pdoId, function () use ($callback, $pdo, $pdoId): int {
                return $callback($pdo, $pdoId);
            }, $attempt, $maxRetry, $delayMs, $onDuplicateCallback);
        });
    }

    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempt Number of attempts
     * @param int $maxRetry Max Number of attempts
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     */
    public function retryConnection(int $pdoId, callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldRetry($throwable) && ($attempt <= $maxRetry || $maxRetry === -1)) {
                $backoff = (1 << $attempt) * $delayMs * 1000; // microseconds
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('pdoId: #%d Retrying #%d in %.2f seconds...', $pdoId, $attempt + 1, $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                ++$attempt;
                $result = $this->retryConnection($pdoId, $callback, $attempt, $maxRetry, $delayMs);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('pdoId: #%d Retry #%d succeeded', $pdoId, $attempt));
                return $result;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('pdoId: #%d Retry #%d failed error: %s', $pdoId, $attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempt Number of attempts
     * @param int $maxRetry Max Number of attempts
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     */
    public function retryConnectionForCreate(
        int $pdoId,
        callable $callback,
        int $attempt = 0,
        int $maxRetry = 5,
        int $delayMs = 100,
        ?callable $onDuplicateCallback = null
    ): int {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Handle duplicate entry errors only when attempt > 0 (i.e., after a retry)
            if ($attempt > 0 && isDuplicateException($throwable)) {
                // Handle duplicate entry errors without retrying
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . ']', sprintf('[DUPLICATE] pdoId: #%d Duplicate entry error encountered: %s', $pdoId, $throwable->getMessage()));
                $info = $this->parseDuplicateError($throwable->getMessage());

                if (!$info['table'] || !$info['column'] || !$info['value']) {
                    logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, '[DuplicateHandler] Failed to parse duplicate error: ' . $throwable->getMessage());
                    throw $throwable;
                }

                $id = $onDuplicateCallback ? $onDuplicateCallback($info) : $this->onDuplicateCallback($info);
                if (!$id) {
                    throw $throwable;
                }
            }

            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldRetry($throwable) && ($attempt <= $maxRetry || $maxRetry === -1)) {
                $backoff = (1 << $attempt) * $delayMs * 1000; // microseconds
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('pdoId: #%d Retrying ForCreate #%d in %.2f seconds...', $pdoId, $attempt + 1, $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                ++$attempt;
                $result = $this->retryConnectionForCreate($pdoId, $callback, $attempt, $maxRetry, $delayMs, $onDuplicateCallback);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('pdoId: #%d Retry ForCreate #%d succeeded', $pdoId, $attempt));
                return $result;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('pdoId: #%d Retry ForCreate #%d failed error: %s', $pdoId, $attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Handle duplicate entry by fetching existing record.
     *
     * @param array{value:string,table:string,column:string} $info
     * @return int|null Primary key of existing record
     *
     * @throws Throwable
     */
    private function onDuplicateCallback(array $info): ?int
    {
        return $this->withConnection(function (PDO $pdo) use ($info) {
            $sql  = sprintf('SELECT id FROM `%s` WHERE `%s` = :value LIMIT 1', $info['table'], $info['column']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['value' => $info['value']]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing['id'] ?? null) {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[DuplicateHandler] Found existing %s.%s = %s', $info['table'], $info['column'], $info['value']));
                return $existing['id'] ?? null;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[DuplicateHandler] No existing record found for %s.%s = %s', $info['table'], $info['column'], $info['value']));
            return null;
        });
    }

    /**
     * Parse a MySQL duplicate entry message and extract
     * the conflicting value, table, and column.
     *
     * Example message:
     *   Integrity constraint violation: 1062 Duplicate entry 'user@example.com' for key 'users.email'
     *
     * @return array{value:string|null,table:string|null,column:string|null}
     */
    private function parseDuplicateError(string $message): array
    {
        // Match MySQL 1062 duplicate entry pattern
        $pattern = "/Duplicate entry '([^']+)' for key '([^']+)'/i";

        if (preg_match($pattern, $message, $matches)) {
            $value     = $matches[1] ?? null; // the duplicate value
            $keyString = $matches[2] ?? null; // like "users.email"

            // Split table.column from key name
            $table  = null;
            $column = null;

            if ($keyString && str_contains($keyString, '.')) {
                [$table, $column] = explode('.', $keyString, 2);
            } else {
                // fallback: MySQL might return only index name like 'PRIMARY'
                $column = $keyString;
            }

            return [
                'value'  => $value,
                'table'  => $table,
                'column' => $column,
            ];
        }

        return [
            'value'  => null,
            'table'  => null,
            'column' => null,
        ];
    }

    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempt Number of attempts
     * @param int $maxRetry Max Number of attempts
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     */
    public function forceRetryConnection(int $pdoId, callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if ($attempt <= $maxRetry || $maxRetry === -1) {
                $backoff = (1 << $attempt) * $delayMs * 1000; // microseconds
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('pdoId: #%d Retrying #%d in %.2f seconds...', $pdoId, $attempt + 1, $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                ++$attempt;
                $result = $this->forceRetryConnection($pdoId, $callback, $attempt, $maxRetry, $delayMs);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('pdoId: #%d Retry #%d succeeded', $pdoId, $attempt));
                return $result;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('pdoId: #%d Retry #%d failed error: %s', $pdoId, $attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Execute a callback inside a DB transaction. Supports nested transactions.
     *
     * @template T
     * @param callable(PDO):T $callback
     * @return T
     *
     * @throws Throwable
     */
    public function withTransaction(callable $callback): mixed
    {
        return $this->withConnection(function (PDO $pdo, int $pdoId) use ($callback) {
            $alreadyInTransaction = $pdo->inTransaction();

            if (!$alreadyInTransaction) {
                $pdo->beginTransaction();
            }

            try {
                /** @var T $result */
                $result = $callback($pdo, $pdoId);

                if (!$alreadyInTransaction) {
                    $pdo->commit();
                }

                return $result;
            } catch (Throwable $throwable) {
                if (!$alreadyInTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $throwable;
            }
        });
    }

    /**
     * Get pool statistics.
     *
     * @return array ['capacity', 'available', 'created', 'in_use']
     */
    public function stats(): array
    {
        return [
            'capacity'  => $this->channel->capacity,
            'available' => $this->channel->length(),
            'created'   => $this->created,
            'in_use'    => max(0, $this->created - $this->channel->length()),
        ];
    }

    /**
     * Auto-scale connections manually.
     * Can be run periodically.
     */
    public function autoScale(): void
    {
        $available       = $this->channel->length();
        $idleBufferCount = (int)($this->max * $this->idleBuffer);
        $upperThreshold  = (int)($idleBufferCount * (1 + $this->margin));
        $lowerThreshold  = min($this->min, (int)($idleBufferCount * (1 - $this->margin)));

        // Scale UP
        if ($available < $lowerThreshold && $this->created < $this->max) {
            $toCreate = min($this->max - $this->created, $lowerThreshold - $available);
            for (
                $i = 0;
                $i < $toCreate;
                ++$i
            ) {
                $success = $this->channel->push($this->make());
                if ($success === false) {
                    throw new ChannelException('Unable to push to channel' . PHP_EOL);
                }
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[SCALE-UP PDO] Created %d connections', $toCreate));
        }

        // Scale DOWN
        if ($available > $upperThreshold && $this->created > $this->min) {
            $toClose = min($this->created - $this->min, $available - $upperThreshold);
            for ($i = 0; $i < $toClose; ++$i) {
                $conn = $this->channel->pop(0.01);
                if ($conn) {
                    unset($conn);
                    $this->created = max(0, $this->created - 1);
                    logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO connection destroyed. Total connections: %d', $this->created));
                }
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[SCALE-DOWN PDO] Closed %d idle connections', $toClose));
        }
    }
}
