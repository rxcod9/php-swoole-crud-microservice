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
use App\Exceptions\PdoConnectionException;
use App\Exceptions\PdoPoolExhaustedException;
use App\Exceptions\PdoPoolNotInitializedException;
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
            $success = $this->channel->push($this->make(0, $maxRetry));
            error_log(sprintf('Pre-created PDO connection #%d', $i + 1));
            if ($success === false) {
                throw new ChannelException('Unable to push to channel' . PHP_EOL);
            }
        }

        $this->initialized = true;
        error_log(sprintf('PDOPool initialized with min=%d, max=%d', $this->min, $this->max));
    }

    /**
     * Create a new PDO connection.
     *
     * @param int $retry Current retry attempt count.
     *
     * @throws RuntimeException
     */
    /**
     * Create a new PDO connection.
     * @param int $retry Current retry attempt count.
     * @throws RuntimeException
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function make(int $retry = 0, int $maxRetry = 5): array
    {
        try {
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
            error_log(sprintf('[CREATE] PDO connection created. Total connections: %d', $this->created));
            return [$pdo, $connectionId];
        } catch (PDOException $pdoException) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldPDORetry($pdoException)) {
                ++$retry;
                if ($retry <= $maxRetry || $maxRetry === -1) {
                    $backoff = (1 << $retry) * 100000; // microseconds
                    error_log(sprintf('[RETRY] Retrying PDO connection in %.2f seconds...', $backoff / 1000000));
                    Coroutine::sleep($backoff / 1000000);
                    return $this->make($retry, $maxRetry);
                }
            }

            error_log(sprintf('[EXCEPTION] PDO connection error: %s | Connections created: %d', $pdoException->getMessage(), $this->created));
            throw new PdoConnectionException(
                sprintf('PDO connection error: %s | Connections created: %d', $pdoException->getMessage(), $this->created),
                $pdoException->getCode(),
                $pdoException
            );
        }
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
            error_log(sprintf('[SCALE-UP PDO] Creating %d new connections (used: %d, available: %d)', $toCreate, $used, $available));

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
            error_log(sprintf('PDO connection destroyed. Total connections: %d', $this->created));
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

            error_log('[PUT] PDO connection returned to pool');
            return;
        }

        // Pool full, let garbage collector close the PDO object
        unset($pdo);
        $this->created = max(0, $this->created - 1);
        error_log(sprintf('PDO connection destroyed. Total connections: %d', $this->created));
        error_log(sprintf('[PUT] Pool full, PDO connection discarded. Total connections: %d', $this->created));
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
    public function withConnection(callable $callback, int $retry = 0, int $maxRetry = 5): mixed
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
                error_log(sprintf('PDO connection destroyed. Total connections: %d', $this->created));
                [$pdo, $pdoId]      = $this->get();
                $this->active[$cid] = [$pdo, $pdoId];
            }
        }

        try {
            return $callback($pdo, $pdoId);
        } catch (PDOException $pdoException) {
            if (shouldPDORetry($pdoException)) {
                ++$retry;
                if ($retry <= $maxRetry || $maxRetry === -1) {
                    $backoff = (1 << $retry) * 100000; // microseconds
                    error_log(sprintf('[RETRY] Retrying PDO connection in %.2f seconds...', $backoff / 1000000));
                    Coroutine::sleep($backoff / 1000000);

                    // Clear the active PDO so next attempt fetches a fresh connection
                    if ($outermost) {
                        unset($this->active[$cid]);
                        $this->created = max(0, $this->created - 1);
                        error_log(sprintf('PDO connection destroyed. Total connections: %d', $this->created));
                    }

                    return $this->withConnection($callback, $retry, $maxRetry);
                }
            }

            throw $pdoException;
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
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempts Number of attempts
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     */
    public function retry(callable $callback, int $attempts = 3, int $delayMs = 100): mixed
    {
        $lastThrowable = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $callback();
            } catch (Throwable $t) {
                $lastThrowable = $t;
                if ($i < $attempts) {
                    usleep($delayMs * 1000); // convert ms to microseconds
                }
            }
        }

        throw $lastThrowable; // all retries failed
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

            error_log(sprintf('[SCALE-UP PDO] Created %d connections', $toCreate));
        }

        // Scale DOWN
        if ($available > $upperThreshold && $this->created > $this->min) {
            $toClose = min($this->created - $this->min, $available - $upperThreshold);
            for ($i = 0; $i < $toClose; ++$i) {
                $conn = $this->channel->pop(0.01);
                if ($conn) {
                    unset($conn);
                    $this->created = max(0, $this->created - 1);
                    error_log(sprintf('PDO connection destroyed. Total connections: %d', $this->created));
                }
            }

            error_log(sprintf('[SCALE-DOWN PDO] Closed %d idle connections', $toClose));
        }
    }
}
