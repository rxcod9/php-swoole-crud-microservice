<?php

/**
 * src/Core/Pools/PDOPool.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
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
    private int $created      = 0;

    // /** @var array<string|int, array{0: PDO, 1: int}> Active PDO instances per coroutine */
    // private array $active = [];

    /**
     * PDOPool constructor.
     *
     * @param array<string, mixed> $conf       MySQL connection config: host, port, user, pass, db, charset, timeout
     * @param int               $min        Minimum connections to pre-create
     * @param int               $max        Maximum connections allowed
     * @param float             $idleBuffer Idle buffer ratio (0-1)
     * @param float             $margin     Scaling margin (0-1)
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
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
    public function init(int $maxRetry = 10): void
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
     * @param int $maxRetry Maximum number of retry attempts.
     * @param int $delayMs Delay between retries in milliseconds.
     * @see retry() in Retryable trait.
     *
     * @return array{0: PDO, 1: int} [PDO instance, connection ID]
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function make(int $attempt = 0, int $maxRetry = 10, int $delayMs = 100): array
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
                $this->conf['options'] ?? [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT         => false, // we manage pool manually
                ]
            );

            $connectionId = $this->getConnectionId($dsn, $pdo);

            ++$this->created;
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO connection created. Total connections: %d', $this->created));
            return [$pdo, $connectionId];
        }, $attempt, $maxRetry, $delayMs);
    }

    private function getConnectionId(string $dsn, PDO $pdo): int
    {
        if (str_starts_with($dsn, 'sqlite:')) {
            return spl_object_id($pdo); // $this->created + 1;
        }

        $pdoIdStmt = $pdo->query('SELECT CONNECTION_ID() AS id');
        return (int) $pdoIdStmt->fetchColumn();
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
        if ($item === false) {
            throw new PdoPoolExhaustedException('PDO pool exhausted', 503);
        }

        [$pdo, $pdoId] = $item;

        // if (!$this->isConnected($pdo)) {
        //     unset($pdo);
        //     unset($pdoId);
        //     logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO dead connection destroyed. Total connections: %d', $this->created));
        //     // create a fresh connection synchronously (preserve previous semantics)

        //     [$pdo, $pdoId] = $this->make();
        //     return [$pdo, $pdoId];
        // }

        return [$pdo, $pdoId];
    }

    /**
     * Lightweight connectivity check. Returns true when a simple query succeeds.
     */
    public function isConnected(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetch(\PDO::FETCH_ASSOC);
            // Clear any remaining result set to be safe in pool context
            $this->clearStatement($stmt);
            return true;
        } catch (PDOException $pdoException) {
            // Connection is dead
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', 'PDO connection check failed: ' . $pdoException->getMessage());
        }

        return false;
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
        // if (!(bool)$this->channel->isFull() && $this->isConnected($pdo)) {
        if (!(bool)$this->channel->isFull()) {
            $success = $this->channel->push([$pdo, $pdoId]);
            if ($success === false) {
                throw new ChannelException('Unable to push to channel' . PHP_EOL);
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'PDO connection returned to pool');
            return;
        }

        // Pool full, let garbage collector close the PDO object
        unset($pdo);
        unset($pdoId);
        // if ((bool)$this->channel->isFull()) {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Pool full, PDO connection discarded. Total connections: %d', $this->created));
        // } else {
        //     logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO dead connection destroyed. Total connections: %d', $this->created));
        // }
    }

    /**
     * Execute a callback within a pooled PDO connection.
     * Re-entrant safe: nested calls reuse the same PDO for the current coroutine.
     *
     * @template T
     * @param callable(PDO,int):T $callback Callback receives PDO instance and its pool ID
     * @return T The result of the callback
     *
     * @throws Throwable Any exception thrown by the callback is propagated
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     */
    public function withConnection(callable $callback): mixed
    {
        Coroutine::getCid();

        // Get a PDO connection from the pool; detects re-entrant usage
        [$pdo, $pdoId, $outermost] = $this->getPdo();

        $result  = null;
        $success = false;

        try {
            // Execute the callback
            $result  = $callback($pdo, $pdoId);
            $success = true;
            return $result;
        } catch (Throwable $throwable) {
            // On exception, discard connection if outermost
            // if ($outermost) {
            unset($pdo);
            unset($pdoId);
            $this->created = max(0, $this->created - 1);
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                sprintf('Exception in callback, PDO discarded. Total connections: %d', $this->created)
            );
            // }
            throw $throwable; // re-throw
        } finally {
            // Return connection to the pool only if callback succeeded
            // if ($success && $outermost) {
            if ($success && isset($pdo) && isset($pdoId)) {
                $this->put($pdo, $pdoId);
            }
        }
    }

    /**
     * Get PDO
     *
     * @return array{PDO, int, bool} [pdo, pdoId, outermost]
     */
    private function getPdo(): array
    {
        $outermost = false;
        // if (!isset($this->active[$cid])) {
        // Outermost call for this coroutine
        [$pdo, $pdoId] = $this->get();
        // $this->active[$cid] = [$pdo, $pdoId];
        $outermost = true;
        return [$pdo, $pdoId, $outermost];
        // }
        // Nested call, reuse the same PDO
        // [$pdo, $pdoId] = $this->active[$cid];
        // check if connection is still alive
        // if ($this->isConnected($pdo)) {
        // return [$pdo, $pdoId, $outermost];
        // }
        // // Connection is dead, remove from active and get a new one
        // unset($this->active[$cid]);
        // $this->created = max(0, $this->created - 1);
        // logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('PDO connection destroyed. Total connections: %d', $this->created));
        // [$pdo, $pdoId]      = $this->get();
        // $this->active[$cid] = [$pdo, $pdoId];
        // return [$pdo, $pdoId, $outermost];
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
    public function withConnectionAndRetry(callable $callback, int $attempt = 0): mixed
    {
        $retryContext = new RetryContext($attempt);
        return $this->retryConnection($retryContext, function () use ($callback): mixed {
            return $this->withConnection(function (PDO $pdo, int $pdoId) use ($callback): mixed {
                return $callback($pdo, $pdoId);
            });
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
    public function withConnectionAndRetryForCreate(string $table, callable $callback, int $attempt = 0, ?callable $onDuplicateCallback = null): int
    {
        $retryContext = new RetryContext($attempt);
        return $this->retryConnectionForCreate($table, $retryContext, function () use ($callback): int {
            return $this->withConnection(function (PDO $pdo, int $pdoId) use ($callback): int {
                return $callback($pdo, $pdoId);
            });
        }, $onDuplicateCallback);
    }

    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     *
     * @throws Throwable
     * @see retry() in Retryable trait.
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function retryConnection(RetryContext $retryContext, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldRetry($throwable) && $retryContext->canRetry()) {
                $backoff = $retryContext->backoff();
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Retrying #%d in %.2f seconds...', $retryContext->attempt + 1, $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                $retryContext->next();
                $result = $this->retryConnection($retryContext, $callback);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Retry #%d succeeded', $retryContext->attempt));
                return $result;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('Retry #%d failed error: %s', $retryContext->attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Retry a callback with exponential backoff and duplicate entry handling.
     *
     * @param callable(): mixed $callback
     *
     * @throws Throwable
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function retryConnectionForCreate(
        string $table,
        RetryContext $retryContext,
        callable $callback,
        ?callable $onDuplicateCallback = null
    ): int {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Handle duplicate errors first
            $id = $this->executeWithDuplicateHandling($table, $throwable, $onDuplicateCallback);
            if ($id !== null) {
                return $id;
            }

            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldForceRetry($throwable) && $retryContext->canRetry()) {
                $backoff = $retryContext->backoff();
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Retrying For Create #%d in %.2f seconds...', $retryContext->attempt + 1, $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                $retryContext->next();
                $result = $this->retryConnectionForCreate($table, $retryContext, $callback, $onDuplicateCallback);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Retry For Create #%d succeeded', $retryContext->attempt));
                return $result;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('Retry For Create #%d failed error: %s', $retryContext->attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Handle duplicate entry exceptions and invoke appropriate callbacks.
     *
     * This method detects duplicate entry SQL errors, validates the parsed
     * duplicate information, and triggers a duplicate handling callback.
     *
     * @param string        $table               The table name expected for duplicate validation.
     * @param Throwable     $throwable           The exception thrown from PDO or Swoole task.
     * @param callable|null $onDuplicateCallback Optional callback to handle the duplicate.
     *
     * @return int|null Result of callback or null if not applicable.
     * @throws Throwable
     */
    private function executeWithDuplicateHandling(
        string $table,
        Throwable $throwable,
        ?callable $onDuplicateCallback = null
    ): ?int {
        if (!$this->isDuplicateThrowable($throwable)) {
            return null;
        }

        $info = $this->parseDuplicateError($throwable->getMessage());

        if (!$this->isValidDuplicateInfo($info, $table, $throwable->getMessage())) {
            return null;
        }

        return $this->invokeDuplicateCallback($info, $onDuplicateCallback);
    }

    /**
     * Check if the throwable represents a duplicate exception.
     */
    private function isDuplicateThrowable(Throwable $throwable): bool
    {
        $isDuplicate = isDuplicateException($throwable);
        if ($isDuplicate) {
            logDebug(self::TAG . ':' . __LINE__, sprintf('[DUPLICATE] Duplicate entry: %s', $throwable->getMessage()));
        }

        return $isDuplicate;
    }

    /**
     * Validate parsed duplicate error info structure.
     * @param array{value:string|null,table:string|null,column:string|null} $info
     */
    private function isValidDuplicateInfo(array $info, string $table, string $errorMessage): bool
    {
        $requiredKeys = ['table', 'column', 'value'];
        foreach ($requiredKeys as $requiredKey) {
            if (isset($info[$requiredKey])) {
                logDebug(self::TAG . ':' . __LINE__, sprintf("[DuplicateHandler] Missing key '%s' in duplicate info for error: %s", $requiredKey, $errorMessage));
                return false;
            }
        }

        if ($info['table'] !== $table) {
            logDebug(self::TAG . ':' . __LINE__, sprintf('[DuplicateHandler] Mismatched table. Expected: %s, Found: %s. Error: %s', $table, $info['table'], $errorMessage));
            return false;
        }

        return true;
    }

    /**
     * Execute provided duplicate handler callback safely.
     * @param array{value:string|null,table:string|null,column:string|null} $info
     */
    private function invokeDuplicateCallback(array $info, ?callable $onDuplicateCallback): ?int
    {
        try {
            return $onDuplicateCallback !== null
                ? $onDuplicateCallback($info)
                : $this->onDuplicateCallback($info);
        } catch (\Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__, '[DuplicateHandler] Callback failed: ' . $throwable->getMessage());
            return null;
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
        return $this->withConnectionAndRetry(function (PDO $pdo) use ($info) {
            $sql  = sprintf('SELECT id FROM `%s` WHERE `%s` = :value LIMIT 1', $info['table'], $info['column']);
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['value' => $info['value']]);

            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing !== false && isset($existing['id'])) {
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

        if (in_array(preg_match($pattern, $message, $matches), [0, false], true)) {
            return [
                'value'  => null,
                'table'  => null,
                'column' => null,
            ];
        }

        $value     = $matches[1]; // the duplicate value
        $keyString = $matches[2]; // like "users.email"

        // Split table.column from key name
        $table  = null;
        $column = null;

        if (str_contains($keyString, '.')) {
            [$table, $column] = explode('.', $keyString, 2);
            return [
                'value'  => $value,
                'table'  => $table,
                'column' => $column,
            ];
        }

        // fallback: MySQL might return only index name like 'PRIMARY'
        $column = $keyString;
        return [
            'value'  => $value,
            'table'  => $table,
            'column' => $column,
        ];
    }

    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     *
     * @throws Throwable
     * @see retry() in Retryable trait.
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function forceRetryConnection(RetryContext $retryContext, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldForceRetry($throwable) && $retryContext->canRetry()) {
                $backoff = $retryContext->backoff();
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Force Retrying #%d in %.2f seconds...', $retryContext->attempt + 1, $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                $retryContext->next();
                $result = $this->forceRetryConnection($retryContext, $callback);
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Force Retry #%d succeeded', $retryContext->attempt));
                return $result;
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('Force Retry #%d failed error: %s', $retryContext->attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Execute a callback inside a DB transaction. Supports nested transactions.
     *
     * @template T
     * @param callable(PDO, int):T $callback
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
     * @return array<string, int> ['capacity', 'available', 'created', 'in_use']
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
     * Manual trigger for auto-scaling logic.
     * Can be called periodically (e.g., via a timer) to adjust pool size.
     *
     * @throws ChannelException If unable to push to channel.
     */
    public function autoScale(): void
    {
        // Current available idle connections in the channel
        $available = $this->channel->length();

        // Determine thresholds based on idle buffer
        $thresholds = $this->calculateThresholds();

        // ----------- Scale UP -----------
        $this->scaleUp($available, $thresholds['lower']);

        // ----------- Scale DOWN -----------
        $this->scaleDown($available, $thresholds['upper']);
    }

    /**
     * Calculate the upper and lower thresholds for scaling operations.
     *
     * @return array{upper:int,lower:int} Threshold values for upper and lower limits
     */
    private function calculateThresholds(): array
    {
        // Number of idle connections we aim to keep in buffer
        $idleBufferCount = (int)($this->max * $this->idleBuffer);

        // Upper threshold — too many idle connections → scale down
        $upperThreshold = (int)($idleBufferCount * (1 + $this->margin));

        // Lower threshold — too few idle connections → scale up
        // Note: min() ensures we never scale below the minimum pool size
        $lowerThreshold = min($this->min, (int)($idleBufferCount * (1 - $this->margin)));

        return [
            'upper' => $upperThreshold,
            'lower' => $lowerThreshold,
        ];
    }

    /**
     * Perform scale-up logic when available idle connections fall below lower threshold.
     *
     * @param int $available       Number of currently available idle connections
     * @param int $lowerThreshold  Threshold below which scaling up should occur
     *
     * @throws ChannelException If unable to push new connections into channel
     */
    private function scaleUp(int $available, int $lowerThreshold): void
    {
        // Condition: available < lowerThreshold → insufficient idle connections
        if ($available < $lowerThreshold && $this->created < $this->max) {
            // Determine how many new connections to create
            $toCreate = min($this->max - $this->created, $lowerThreshold - $available);

            // Attempt to create and push new PDO connections
            for ($i = 0; $i < $toCreate; ++$i) {
                $success = $this->channel->push($this->make());
                if ($success === false) {
                    // Fail early if channel is full or push operation fails
                    throw new ChannelException('Unable to push to channel' . PHP_EOL);
                }
            }

            // Log pool expansion event
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                sprintf('[SCALE-UP PDO] Created %d connections', $toCreate)
            );
        }
    }

    /**
     * Perform scale-down logic when available idle connections exceed upper threshold.
     *
     * @param int $available       Number of currently available idle connections
     * @param int $upperThreshold  Threshold above which scaling down should occur
     */
    private function scaleDown(int $available, int $upperThreshold): void
    {
        // Condition: available > upperThreshold → too many idle connections
        if ($available > $upperThreshold && $this->created > $this->min) {
            // Determine number of excess idle connections to close
            $excessIdle = $available - $upperThreshold;
            $toClose    = min($this->created - $this->min, $excessIdle);

            // Attempt to close and remove idle PDO connections
            for ($i = 0; $i < $toClose; ++$i) {
                // Non-blocking pop to fetch idle connection
                $conn = $this->channel->pop(0.01);

                // Ensure we are handling a valid PDO tuple structure
                if ($conn !== false && is_array($conn)) {
                    [$pdo, $pdoId] = $conn;

                    // Close PDO connection
                    unset($pdo);
                    unset($pdoId);
                    // Update counter to reflect closed connections
                    $this->created = max(0, $this->created - 1);

                    // Log connection close event for traceability
                    logDebug(
                        self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                        sprintf('PDO connection closed. Total connections: %d', $this->created)
                    );
                }
            }

            // Log pool contraction summary
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                sprintf('[SCALE-DOWN PDO] Closed %d idle connections', $toClose)
            );
        }
    }
}
