<?php

/**
 * src/Core/Pools/RedisPool.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Pools/RedisPool.php
 */
declare(strict_types=1);

namespace App\Core\Pools;

use App\Exceptions\ChannelException;
use App\Exceptions\RedisConnectionException;
use App\Exceptions\RedisConnectionFailedException;
use App\Exceptions\RedisPoolExhaustedException;
use App\Exceptions\RedisPoolNotInitializedException;
use App\Exceptions\UnsupportedRedisCommandException;
use Redis;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * Class RedisPool
 * Coroutine-safe Redis connection pool with auto-scaling.
 * Handles connection creation, scaling, health checks, and command execution.
 * Uses php-redis extension (phpredis) instead of Swoole\Coroutine\Redis.
 *
 * @category  Core
 * @package   App\Core\Pools
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class RedisPool
{
    public const TAG = 'RedisPool';

    /**
     * @var Channel The coroutine channel used for managing Redis connections.
     *              Acts as a queue to hold available connections.
     */
    private readonly Channel $channel;

    /** @var int Number of created Redis connections. */
    private int $created = 0;

    /** @var bool Indicates if the pool has been initialized and ready for use. */
    private bool $initialized = false; // e.g., 0.025 = 5% margin

    /**
     * RedisPool constructor.
     *
     * Initializes the pool with min connections and sets up the channel.
     * Auto-scaling parameters are also configured.
     *
     * @param array<string, mixed> $conf       Redis connection config (host, port, password, database)
     * @param int               $min        Minimum connections to pre-create
     * @param int               $max        Maximum connections allowed
     * @param float             $idleBuffer Fraction of max pool used to decide scale-down
     * @param float             $margin     Margin to avoid oscillation in auto-scaling
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private array $conf,
        private readonly int $min = 5,
        private readonly int $max = 200,
        private readonly float $idleBuffer = 0.025,
        private readonly float $margin = 0.025
    ) {
        // Initialize channel with maximum capacity
        $this->channel = new Channel($this->max);
    }

    /**
     * Initialize pool inside a coroutine (e.g. from onWorkerStart).
     * Pre-creates minimum connections to avoid delays during first use.
     *
     * @param int $maxRetry Maximum retry attempts for connection creation (-1 for infinite retries)
     *
     * @throws ChannelException If unable to push to channel
     */
    public function init(int $maxRetry = 10): void
    {
        // Pre-create minimum connections to avoid startup delays
        for ($i = 0; $i < $this->min; ++$i) {
            $conn = $this->make(0, $maxRetry);

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Pre-created Redis connection #%d', $i + 1));
            $success = $this->channel->push($conn);
            if ($success === false) {
                throw new ChannelException('Unable to push to channel' . PHP_EOL);
            }
        }

        $this->initialized = true;
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('RedisPool initialized with min=%d, max=%d', $this->min, $this->max));
    }

    /**
     * Create a new Redis connection.
     * Uses exponential backoff for retry on failure.
     *
     * @param int $attempt   Current retry attempt count.
     * @param int $maxRetry  Maximum retry attempts (-1 for infinite retries).
     *
     * @throws RedisConnectionFailedException If connection fails after retries.
     * @throws RedisConnectionException       If connection fails due to other errors.
     */
    private function make(int $attempt = 0, int $maxRetry = 10): Redis
    {
        try {
            // ---------------------------------------
            // Step 1: Initialize Redis instance
            // ---------------------------------------
            $redis = $this->initializeRedisConnection();

            // ---------------------------------------
            // Step 2: Authenticate and Select DB
            // ---------------------------------------
            $this->configureRedisConnection($redis);

            ++$this->created;
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                sprintf('Redis connection created. Total connections: %d', $this->created)
            );

            return $redis;
        } catch (Throwable $throwable) {
            // ---------------------------------------
            // Step 3: Handle retry logic on failure
            // ---------------------------------------
            if ($this->shouldRetry($throwable, $attempt, $maxRetry)) {
                $this->retryWithBackoff($attempt);
                return $this->make(++$attempt, $maxRetry);
            }

            // ---------------------------------------
            // Step 4: Handle permanent connection failure
            // ---------------------------------------
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception',
                sprintf('Redis connection error: %s | Pool created: %d', $throwable->getMessage(), $this->created)
            );

            throw new RedisConnectionException(
                sprintf('Redis connection error: %s | Pool created: %d', $throwable->getMessage(), $this->created),
                $throwable->getCode(),
                $throwable
            );
        }
    }

    /**
     * Initialize a new Redis instance and attempt to connect.
     *
     * @throws RedisConnectionFailedException If unable to establish initial connection.
     */
    private function initializeRedisConnection(): Redis
    {
        $redis = new Redis();
        $host  = $this->conf['host'] ?? '127.0.0.1';
        $port  = $this->conf['port'] ?? 6379;

        // Attempt connection
        $connected = $redis->connect($host, $port);

        if (!$connected) {
            throw new RedisConnectionFailedException(
                sprintf('Redis connection failed [%s:%d] | Pool created: %d', $host, $port, $this->created)
            );
        }

        return $redis;
    }

    /**
     * Configure an active Redis connection by setting password and database if provided.
     */
    private function configureRedisConnection(Redis $redis): void
    {
        // Authenticate if password is provided
        if (isset($this->conf['password']) && $this->conf['password'] !== '') {
            $redis->auth($this->conf['password']);
        }

        // Select database if specified
        if (isset($this->conf['database']) && $this->conf['database'] !== '') {
            $redis->select($this->conf['database']);
        }
    }

    /**
     * Determines if the current error should trigger a retry attempt.
     */
    private function shouldRetry(Throwable $throwable, int $attempt, int $maxRetry): bool
    {
        return shouldRedisRetry($throwable) && ($attempt <= $maxRetry || $maxRetry === -1);
    }

    /**
     * Handles exponential backoff delay between retry attempts.
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function retryWithBackoff(int $attempt): void
    {
        $backoff = (1 << $attempt) * 100000; // exponential backoff in microseconds
        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
            sprintf('Retrying Redis connection in %.2f seconds...', $backoff / 1000000)
        );

        Coroutine::sleep($backoff / 1000000);
    }

    /**
     * Get a Redis connection from the pool.
     * Auto-scales pool size if needed.
     *
     * @param float $timeout Seconds to wait for a connection
     *
     * @throws RedisPoolNotInitializedException If pool is not initialized
     * @throws RedisPoolExhaustedException If pool is exhausted or not initialized
     */
    public function get(float $timeout = 1.0): Redis
    {
        if (!$this->initialized) {
            throw new RedisPoolNotInitializedException('Redis pool not initialized yet');
        }

        $available = $this->channel->length();
        $used      = $this->created - $available;

        // Auto-scale up
        if (($available <= 1) && $this->created < $this->max) {
            $toCreate = 1;
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[SCALE-UP MySQL] Creating %d new connections (used: %d, available: %d)', $toCreate, $used, $available));
            return $this->make();
        }

        // Pop a connection from the channel (waits up to $timeout seconds)
        $conn = $this->channel->pop($timeout);
        if ($conn === false) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[ERROR] Redis pool exhausted (timeout=%.2f, available=%d, used=%d, created=%d)', $timeout, $available, $used, $this->created));
            throw new RedisPoolExhaustedException('Redis pool exhausted', 503);
        }

        if (!$conn->isConnected()) {
            $this->created = max(0, $this->created - 1);
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Redis connection destroyed. Total connections: %d', $this->created));
            // create a fresh connection synchronously (preserve previous semantics)
            return $this->make();
        }

        return $conn;
    }

    /**
     * Return a Redis connection back to the pool.
     *
     * @param Redis $redis The Redis connection to return.
     *
     * @throws ChannelException If unable to push to channel
     */
    public function put(Redis $redis): void
    {
        if (!(bool) $this->channel->isFull() && $redis->isConnected()) {
            $success = $this->channel->push($redis);
            if ($success === false) {
                throw new ChannelException('Unable to push to channel' . PHP_EOL);
            }

            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, '[PUT] Redis connection returned to pool');
            return;
        }

        // Pool full, let garbage collector close the Redis object
        $redis->close();
        $this->created = max(0, $this->created - 1);

        if ((bool) $this->channel->isFull()) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[PUT] Pool full, Redis connection discarded. Total connections: %d', $this->created));
            return;
        }

        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Redis dead connection closed. Total connections: %d', $this->created));
    }

    /**
     * Execute a Redis command.
     *
     * Example:
     *   $redis->command('set', ['foo', 'bar']);
     *   $val = $redis->command('get', ['foo']);
     *
     * @param string            $cmd  Redis command (lowercase or uppercase)
     * @param array<int, mixed> $args Arguments to the Redis command
     *
     * @throws UnsupportedRedisCommandException If command is unsupported
     *
     * @return mixed Result of Redis command
     */
    public function command(string $cmd, array $args = []): mixed
    {
        /** @var \Redis $redis */
        $redis = $this->get();
        // Ensure connection is returned after use
        defer(fn () => $this->put($redis));

        $cmd = strtolower($cmd);

        // Check method exists
        if (!method_exists($redis, $cmd)) {
            throw new UnsupportedRedisCommandException('Unsupported Redis command: ' . $cmd);
        }

        // Execute the command dynamically
        return call_user_func_array([$redis, $cmd], $args);
    }

    /**
     * Get current pool statistics.
     *
     * @return array<string, int> Associative array with 'capacity', 'available', 'created', and 'in_use' keys.
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

            // Attempt to create and push new Redis connections
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
                sprintf('[SCALE-UP Redis] Created %d connections', $toCreate)
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

            // Attempt to close and remove idle Redis connections
            for ($i = 0; $i < $toClose; ++$i) {
                // Non-blocking pop to fetch idle connection
                $conn = $this->channel->pop(0.01);
                if ($conn !== false && $conn instanceof Redis) {
                    // Close the Redis connection gracefully
                    $conn->close();

                    // Decrement the internal created connection counter
                    $this->created = max(0, $this->created - 1);

                    // Log every connection closed for traceability
                    logDebug(
                        self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                        sprintf('Redis connection closed. Total connections: %d', $this->created)
                    );
                }
            }

            // Log pool contraction event
            logDebug(
                self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
                sprintf('[SCALE-DOWN Redis] Closed %d idle connections', $toClose)
            );
        }
    }
}
