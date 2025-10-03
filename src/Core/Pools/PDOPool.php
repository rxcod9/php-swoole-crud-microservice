<?php

/**
 * src/Core/Pools/PDOPool.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core\Pools
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Pools/PDOPool.php
 */
declare(strict_types=1);

namespace App\Core\Pools;

use App\Exceptions\ChannelException;
use App\Exceptions\PdoConnectionException;
use App\Exceptions\PdoPoolExhaustedException;
use App\Exceptions\PdoPoolNotInitializedException;
use PDO;
use PDOException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Class PDOPool
 * Coroutine-safe PDO connection pool for MySQL.
 * Automatically scales pool size based on usage.
 *
 * @category Core
 * @package  App\Core\Pools
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class PDOPool
{
    private readonly Channel $channel;

    // PDO/MySQL connection configuration
    private int $created = 0;

    // Total connections created
    private bool $initialized = false;                       // Scaling margin (0-1)

    /**
     * Re-entrant connection stack to track nested withConnection calls
     */
    private array $connectionStack = [];

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
     * Initialize pool inside a coroutine (e.g. from onWorkerStart).
     */
    public function init(int $maxRetry = 5): void
    {
        // Pre-create minimum connections to avoid startup delays
        for ($i = 0; $i < $this->min; ++$i) {
            $conn = $this->make(0, $maxRetry);

            error_log(sprintf('Pre-created PDO connection #%d', $i + 1));
            $success = $this->channel->push($conn);
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
    private function make(int $retry = 0, int $maxRetry = 5): PDO
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
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => false, // we manage pool manually
                ]
            );

            ++$this->created;
            error_log(sprintf(
                '[CREATE] PDO connection created. Total connections: %d',
                $this->created
            ));
            return $pdo;
        } catch (PDOException $pdoException) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldPDORetry($pdoException)) {
                ++$retry;
                error_log(var_export($retry <= $maxRetry, true) . ' ' . var_export($maxRetry === -1, true));
                if ($retry <= $maxRetry || $maxRetry === -1) {
                    $backoff = (1 << $retry) * 100000; // microseconds
                    error_log(sprintf('[RETRY] Retrying PDO connection in %.2f seconds...', $backoff / 1000000));
                    Coroutine::sleep($backoff / 1000000);
                    return $this->make($retry, $maxRetry);
                }
            }

            error_log(sprintf(
                '[EXCEPTION] PDO connection error: %s | Connections created: %d',
                $pdoException->getMessage(),
                $this->created
            ));
            throw new PdoConnectionException(
                sprintf('PDO connection error: %s | Connections created: %d', $pdoException->getMessage(), $this->created),
                $pdoException->getCode(),
                $pdoException
            );
        }
    }

    /**
     * Get a PDO connection from the pool.
     *
     * @param float $timeout Timeout in seconds to wait for a connection
     *
     * @throws RuntimeException
     */
    public function get(float $timeout = 1.0): PDO
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
            for ($i = 0; $i < $toCreate; ++$i) {
                $success = $this->channel->push($this->make());
                if ($success === false) {
                    throw new ChannelException('Unable to push to channel' . PHP_EOL);
                }
            }
        }

        $conn = $this->channel->pop($timeout);
        if (!$conn) {
            throw new PdoPoolExhaustedException('PDO pool exhausted', 503);
        }

        return $conn;
    }

    /**
     * Return a PDO connection to the pool.
     */
    public function put(PDO $pdo): void
    {
        if ($this->channel->isFull()) {
            unset($pdo);
            $pdo = null;
            --$this->created;
            error_log(sprintf('[PUT] Pool full, PDO connection discarded. Total connections: %d', $this->created));
            return;
        }

        if (!$this->isConnected($pdo)) {
            unset($pdo);
            $pdo = null;
            --$this->created;
            error_log('[PUT] Dead PDO connection discarded');
            return;
        }

        $success = $this->channel->push($pdo);
        if ($success === false) {
            throw new ChannelException('Unable to push to channel' . PHP_EOL);
        }

        error_log('[PUT] PDO connection returned to pool');
    }

    /**
     * Check if a PDO connection is alive.
     */
    public function isConnected(PDO $pdo): bool
    {
        try {
            // Lightweight query to check connection
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchColumn();
            return true;
        } catch (PDOException $pdoException) {
            // Connection dropped
            return false;
        }
    }

    /**
     * Execute a callback within a pooled PDO connection.
     * Re-entrant safe: nested calls reuse the same PDO.
     *
     * @template T
     *
     * @param callable(PDO): T $callback Callback receives PDO and returns any type
     *
     * @throws \Throwable Any exception thrown by the callback
     *
     * @return T Whatever the callback returns
     */
    public function withConnection(callable $callback): mixed
    {
        // Check if we are already inside a connection (nested call)
        $existing = end($this->connectionStack) ?: null;

        if ($existing) {
            // Nested call, reuse same PDO
            return $callback($existing);
        }

        // Outermost call: acquire a connection from pool
        $pdo                     = $this->get();
        $this->connectionStack[] = $pdo;
        $returnedToPool          = false;

        try {
            /** @var T $result */
            $result = $callback($pdo);

            // Return connection to pool
            $this->put($pdo);
            $returnedToPool = true;

            array_pop($this->connectionStack);

            return $result;
        } catch (\Throwable $throwable) {
            // On exception, discard connection
            unset($pdo);
            $pdo = null;

            array_pop($this->connectionStack);
            throw $throwable;
        }
    }

    /**
     * Execute a callback within a database transaction.
     * Re-entrant safe: nested transactions reuse the same PDO if already in a transaction.
     *
     * @template T
     *
     * @param callable(PDO): T $callback Callback receives PDO and returns any type
     *
     * @throws \Throwable Any exception thrown by the callback
     *
     * @return T Whatever the callback returns
     */
    public function withTransaction(callable $callback): mixed
    {
        return $this->withConnection(function (PDO $pdo) use ($callback) {
            $alreadyInTransaction = $pdo->inTransaction();

            if (!$alreadyInTransaction) {
                $pdo->beginTransaction();
            }

            try {
                /** @var T $result */
                $result = $callback($pdo);

                if (!$alreadyInTransaction) {
                    $pdo->commit();
                }

                return $result;
            } catch (\Throwable $throwable) {
                if (!$alreadyInTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $throwable;
            }
        });
    }

    /**
     * Check if currently inside a pooled connection
     */
    public function isWithinConnection(): bool
    {
        return $this->connectionStack !== [];
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
            'in_use'    => $this->created - $this->channel->length(),
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
            for ($i = 0; $i < $toCreate; ++$i) {
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
                    --$this->created;
                }
            }

            error_log(sprintf('[SCALE-DOWN PDO] Closed %d idle connections', $toClose));
        }
    }
}
