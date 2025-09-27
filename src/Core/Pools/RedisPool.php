<?php

declare(strict_types=1);

namespace App\Core\Pools;

use Redis;
use RuntimeException;

use function sprintf;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * Class RedisPool
 *
 * Coroutine-safe Redis connection pool with auto-scaling.
 * Handles connection creation, scaling, health checks, and command execution.
 * Uses php-redis extension (phpredis) instead of Swoole\Coroutine\Redis.
 *
 * @package App\Core
 */
final class RedisPool
{
    /**
     * @var Channel The coroutine channel used for managing Redis connections.
     *              Acts as a queue to hold available connections.
     */
    private Channel $channel;

    /**
     * @var int Minimum number of connections to maintain in the pool.
     */
    private int $min;

    /**
     * @var int Maximum number of connections allowed in the pool.
     */
    private int $max;

    /**
     * @var array Redis connection configuration (host, port, password, database).
     */
    private array $conf;

    /**
     * @var int Number of created Redis connections.
     */
    private int $created = 0;

    /**
     * @var bool Indicates if the pool has been initialized and ready for use.
     */
    private bool $initialized = false;

    /**
     * @var float Idle buffer ratio to trigger scaling down (0 to 1).
     */
    private float $idleBuffer; // e.g., 0.05 = 5% of max connections

    /**
     * @var float Exhausted buffer ratio to trigger scaling up (0 to 1).
     */
    private float $margin; // e.g., 0.05 = 5% margin

    /**
     * RedisPool constructor.
     *
     * Initializes the pool with min connections and sets up the channel.
     * Auto-scaling parameters are also configured.
     *
     * @param array $conf Redis connection config (host, port, password, database)
     * @param int $min Minimum connections to pre-create
     * @param int $max Maximum connections allowed
     * @param float $idleBuffer Fraction of max pool used to decide scale-down
     * @param float $margin Margin to avoid oscillation in auto-scaling
     */
    public function __construct(array $conf, int $min = 5, int $max = 200, float $idleBuffer = 0.05, float $margin = 0.05)
    {
        $this->conf = $conf;
        $this->min = $min;
        $this->max = $max;
        $this->idleBuffer = $idleBuffer;
        $this->margin = $margin;

        // Initialize channel with maximum capacity
        $this->channel = new Channel($max);

        // Pre-create minimum connections to avoid startup delays
        for ($i = 0; $i < $min; $i++) {
            $conn = $this->make();
            error_log(sprintf('[%s] Pre-created Redis connection #%d', date('Y-m-d H:i:s'), $i + 1));
            $this->channel->push($conn);
        }

        $this->initialized = true;
        error_log(sprintf('[%s] RedisPool initialized with min=%d, max=%d', date('Y-m-d H:i:s'), $min, $max));
    }

    /**
     * Create a new Redis connection.
     * Uses exponential backoff for retry on failure.
     *
     * @param int $retry Current retry attempt count.
     * @throws RuntimeException If connection fails after retries.
     */
    private function make(int $retry = 0): Redis
    {
        try {
            $redis = new Redis();

            // Attempt connection
            $connected = $redis->connect($this->conf['host'] ?? '127.0.0.1', $this->conf['port'] ?? 6379);

            // Authenticate if password is provided
            if ($connected && !empty($this->conf['password'])) {
                $redis->auth($this->conf['password']);
            }

            // Select database if specified
            if ($connected && isset($this->conf['database'])) {
                $redis->select($this->conf['database']);
            }

            if (!$connected) {
                throw new RuntimeException("Redis connection failed | Pool created: {$this->created}");
            }

            $this->created++;
            error_log(sprintf('[%s] Redis connection created. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
            return $redis;
        } catch (Throwable $e) {
            // Retry with exponential backoff
            $retry++;
            if ($retry <= 3) {
                $backoff = (1 << $retry) * 100000; // microseconds
                error_log(sprintf('[%s] [RETRY] Retrying Redis connection in %.2f seconds...', date('Y-m-d H:i:s'), $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                return $this->make($retry);
            }

            // If all retries fail, throw exception
            error_log(sprintf('[%s] EXCEPTION Redis connection error: %s | Pool created: %d', date('Y-m-d H:i:s'), $e->getMessage(), $this->created));
            throw new RuntimeException("Redis connection error: {$e->getMessage()} | Pool created: {$this->created}");
        }
    }

    /**
     * Get a Redis connection from the pool.
     * Auto-scales pool size if needed.
     *
     * @param float $timeout Seconds to wait for a connection
     * @throws RuntimeException If pool is exhausted or not initialized
     */
    public function get(float $timeout = 1.0): Redis
    {
        if (!$this->initialized) {
            throw new RuntimeException('Redis pool not initialized yet');
        }

        $available = $this->channel->length();
        $used = $this->created - $available;

        // Auto-scale up if pool is low on available connections
        if (($available <= 1) && $this->created < $this->max) {
            $toCreate = 1; // create minimum 1 new connection
            error_log(sprintf('[%s] [SCALE-UP Redis] Creating %d new connections (used: %d, available: %d)', date('Y-m-d H:i:s'), $toCreate, $used, $available));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
        }

        // Pop a connection from the channel (waits up to $timeout seconds)
        $conn = $this->channel->pop($timeout);
        if (!$conn) {
            error_log(sprintf('[%s] [ERROR] Redis pool exhausted (timeout=%.2f, available=%d, used=%d, created=%d)', date('Y-m-d H:i:s'), $timeout, $available, $used, $this->created));
            throw new RuntimeException('Redis pool exhausted', 503);
        }

        return $conn;
    }

    /**
     * Return a Redis connection back to the pool.
     *
     * @param Redis $conn The Redis connection to return.
     */
    public function put(Redis $conn): void
    {
        if (!$this->channel->isFull()) {
            $this->channel->push($conn);
            error_log(sprintf('[%s] Redis connection returned to pool', date('Y-m-d H:i:s')));
        } else {
            // Pool is full; close connection
            $conn->close();
            $this->created--;
            error_log(sprintf('[%s] Pool full, closed Redis connection. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
        }
    }

    /**
     * Execute a Redis command.
     *
     * Example:
     *   $redis->command('set', ['foo', 'bar']);
     *   $val = $redis->command('get', ['foo']);
     *
     * @param string $cmd Redis command (lowercase or uppercase)
     * @param array $args Arguments to the Redis command
     * @return mixed Result of Redis command
     * @throws RuntimeException If command is unsupported
     */
    public function command(string $cmd, array $args = []): mixed
    {
        $conn = $this->get();
        // Ensure connection is returned after use
        defer(fn () => isset($conn) && $this->put($conn));

        $cmd = strtolower($cmd);

        // Check method exists
        if (!method_exists($conn, $cmd)) {
            throw new RuntimeException("Unsupported Redis command: {$cmd}");
        }

        // Execute the command dynamically
        return $conn->{$cmd}(...$args);
    }

    /**
     * Get current pool statistics.
     *
     * @return array Associative array with 'capacity', 'available', 'created', and 'in_use' keys.
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
     * Manual trigger for auto-scaling logic.
     * Can be called periodically (e.g., via a timer) to adjust pool size.
     *
     */
    public function autoScale(): void
    {
        $available = $this->channel->length();
        $used = max(0, $this->created - $available);

        // Determine thresholds based on idle buffer
        $idleBufferCount = (int)($this->max * $this->idleBuffer);
        $upperThreshold = (int)($idleBufferCount * (1 + $this->margin)); // scale down
        $lowerThreshold = min($this->min, (int)($idleBufferCount * (1 - $this->margin))); // scale up

        // ----------- Scale UP -----------
        if ($available < $lowerThreshold && $this->created < $this->max) {
            $toCreate = min($this->max - $this->created, $lowerThreshold - $available);
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
        }

        // ----------- Scale DOWN -----------
        if ($available > $upperThreshold && $this->created > $this->min) {
            $excessIdle = $available - $upperThreshold;
            $toClose = min($this->created - $this->min, $excessIdle);
            for ($i = 0; $i < $toClose; $i++) {
                $conn = $this->channel->pop(0.01); // non-blocking pop
                if ($conn) {
                    $conn->close();
                    $this->created--;
                }
            }
        }
    }
}
