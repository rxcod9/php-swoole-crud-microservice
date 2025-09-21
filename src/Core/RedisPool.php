<?php

namespace App\Core;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Redis;

/**
 * Class RedisPool
 *
 * Coroutine-safe Redis connection pool with auto-scaling.
 * Handles connection creation, scaling, health checks, and command execution.
 *
 * @package App\Core
 */
final class RedisPool
{
    /**
     * @var Channel The coroutine channel used for managing Redis connections.
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
     * @var array Redis connection configuration.
     */
    private array $conf;

    /**
     * @var int Number of created Redis connections.
     */
    private int $created = 0;

    /**
     * @var bool Indicates if the pool has been initialized.
     */
    private bool $initialized = false;

    /**
     * @var float Idle buffer ratio to trigger scaling down (0 to 1).
     */
    private float $idleBuffer; // e.g., 0.05 = 5%

    /**
     * @var float Exhausted buffer ratio to trigger scaling up (0 to 1).
     */
    private float $margin;     // e.g., 0.05 = 5% margin

    /**
     * RedisPool constructor.
     *
     * @param array $conf Redis connection config (host, port, password, database)
     * @param int $min Minimum connections to pre-create
     * @param int $max Maximum connections allowed
     */
    public function __construct(array $conf, int $min = 5, int $max = 200, float $idleBuffer = 0.05, float $margin = 0.05)
    {
        $this->conf = $conf;
        $this->min = $min;
        $this->max = $max;
        $this->idleBuffer = $idleBuffer;
        $this->margin = $margin;

        $this->channel = new Channel($max);

        // Pre-create minimum connections
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
     * Uses exponential backoff for retries on failure.
     * 
     * @param int $retry Current retry attempt count.
     *
     * @return Redis
     * @throws \RuntimeException If connection fails.
     */
    private function make(int $retry = 0): Redis
    {
        try {
            $redis = new Redis();
            $connected = $redis->connect($this->conf['host'] ?? '127.0.0.1', $this->conf['port'] ?? 6379);

            // Password authentication if needed
            if ($connected && !empty($this->conf['password'])) {
                $redis->auth($this->conf['password']);
            }
            // Select database if specified
            if ($connected && isset($this->conf['database'])) {
                $redis->select($this->conf['database']);
            }

            // Check connection
            if (!$connected) {
                error_log(sprintf('[%s] ERROR Redis connection failed: %s | Pool created: %d', date('Y-m-d H:i:s'), $redis->connect_error, $this->created));
                throw new \RuntimeException("Redis connection failed: {$redis->connect_error} | Pool created: {$this->created}");
            }

            $this->created++;
            error_log(sprintf(
                '[%s] Redis connection created. Total connections: %d',
                date('Y-m-d H:i:s'),
                $this->created
            ));
            return $redis;
        } catch (\Throwable $e) {
            // retry with exponential backoff
            $retry++;
            if ($retry <= 3) {
                $backoff = (1 << $retry) * 100000; // exponential backoff in microseconds
                error_log(sprintf('[%s] [RETRY] Retrying MySQL connection in %.2f seconds...', date('Y-m-d H:i:s'), $backoff / 1000000));
                Coroutine::sleep($backoff / 1000000);
                return $this->make($retry);
            }
            error_log(sprintf(
                '[%s] EXCEPTION Redis connection error: %s | Pool created: %d',
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $this->created
            ));
            throw new \RuntimeException("Redis connection error: {$e->getMessage()} | Pool created: {$this->created}");
        }
    }

    /**
     * Get a Redis connection from the pool.
     * Auto-scales pool size based on usage patterns.
     *
     * @param float $timeout Timeout in seconds to wait for a connection.
     * @return Redis
     * @throws \RuntimeException If pool is exhausted or not initialized.
     */
    public function get(float $timeout = 1.0): Redis
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Redis pool not initialized yet');
        }

        // Auto-scale up
        $available = $this->channel->length();
        $used = $this->created - $available;

        // --- Auto-scale up ---
        if (($available <= 1) && $this->created < $this->max) {
            $toCreate = 1; // min($this->max - $this->created, max(1, (int)($this->max * 0.05)));
            error_log(sprintf('[%s] [SCALE-UP Redis] Creating %d new connections (used: %d, available: %d)', date('Y-m-d H:i:s'), $toCreate, $used, $available));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
        }

        $conn = $this->channel->pop($timeout);
        if (!$conn) {
            error_log(sprintf('[%s] [ERROR] Redis pool exhausted (timeout=%.2f, available=%d, used=%d, created=%d)', date('Y-m-d H:i:s'), $timeout, $available, $used, $this->created));
            throw new \RuntimeException('Redis pool exhausted', 503);
        }

        // // Health check: set/get/del a random key
        // $key = '__redis_pool_health_' . bin2hex(random_bytes(8));
        // $value = bin2hex(random_bytes(16));

        // // Try set
        // $setResult = $conn->set($key, $value);
        // if ($setResult !== true) {
        //     error_log(sprintf('[%s] WARNING: Redis SET health check failed. Key: %s', date('Y-m-d H:i:s'), $key));
        //     $conn = $this->make();
        // } else {
        //     // Try get
        //     $getResult = $conn->get($key);
        //     if ($getResult !== $value) {
        //         error_log(sprintf('[%s] WARNING: Redis GET health check failed. Key: %s', date('Y-m-d H:i:s'), $key));
        //         $conn = $this->make();
        //     }
        //     // Try delete
        //     $conn->del($key);
        //     error_log(sprintf('[%s] Redis connection health check passed (set/get/del)', date('Y-m-d H:i:s')));
        // }

        return $conn;
    }

    /**
     * Manually trigger auto-scaling logic.
     * Can be called periodically to adjust pool size.
     *
     * @return void
     * @throws \RuntimeException If pool is exhausted or not initialized.
     */
    public function autoScale(): void
    {
        $available = $this->channel->length();
        $used = max(0, $this->created - $available);
        $idleBufferCount = (int)($this->max * $this->idleBuffer);
        $upperThreshold = (int)($idleBufferCount * (1 + $this->margin)); // scale down threshold
        $lowerThreshold = min($this->min, (int)($idleBufferCount * (1 - $this->margin))); // scale up threshold

        // ----------- Scale UP if idle connections are below lowerThreshold -----------
        if ($available < $lowerThreshold && $this->created < $this->max) {
            $toCreate = min($this->max - $this->created, $lowerThreshold - $available);
            error_log(sprintf('[%s] [SCALE-UP Redis] Creating %d new connections (used: %d, available: %d)', date('Y-m-d H:i:s'), $toCreate, $used, $available));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
        }

        // ----------- Scale DOWN if idle connections exceed upperThreshold -----------
        if ($available > $upperThreshold && $this->created > $this->min) {
            $excessIdle = $available - $upperThreshold;
            $toClose = min($this->created - $this->min, $excessIdle);
            error_log(sprintf('[%s] Auto-scaling DOWN: Closing %d idle Redis connections', date('Y-m-d H:i:s'), $toClose));
            for ($i = 0; $i < $toClose; $i++) {
                $conn = $this->channel->pop(0.01); // non-blocking get
                if ($conn) {
                    $conn->close();
                    $this->created--;
                    error_log(sprintf('[%s] Closed idle Redis connection. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
                }
            }
        }
    }

    /**
     * Return a Redis connection back to the pool.
     *
     * @param Redis $conn The Redis connection to return.
     * @return void
     */
    public function put(Redis $conn): void
    {
        if (!$this->channel->isFull()) {
            $this->channel->push($conn);
            error_log(sprintf('[%s] Redis connection returned to pool', date('Y-m-d H:i:s')));
        } else {
            // Pool is full, close the connection
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
     * @param string $cmd Redis command
     * @param array $args Command arguments
     * @return mixed
     * @throws \RuntimeException If command is unsupported.
     */
    public function command(string $cmd, array $args = []): mixed
    {
        $conn = $this->get();
        defer(fn() => isset($conn) && $conn->connected && $this->put($conn));

        // Normalize command name
        $cmd = strtolower($cmd);

        // Dynamically call Redis method
        if (!method_exists($conn, $cmd)) {
            error_log(sprintf('[%s] ERROR Unsupported Redis command: %s', date('Y-m-d H:i:s'), $cmd));
            error_log(sprintf('[%s] Supported Redis commands: %s', date('Y-m-d H:i:s'), implode(', ', get_class_methods($conn))));
            error_log(sprintf('[%s] Redis ping method definition: %s', date('Y-m-d H:i:s'), var_export((new \ReflectionMethod($conn, 'ping')), true)));
            error_log(sprintf('[%s] Redis connection object: %s', date('Y-m-d H:i:s'), var_export($conn, true)));

            throw new \RuntimeException("Unsupported Redis command: {$cmd}");
        }

        error_log(sprintf('[%s] Executing Redis command: %s', date('Y-m-d H:i:s'), $cmd));
        $result = $conn->clear();
        $result = $conn->{$cmd}(...$args);

        return $result;
    }

    /**
     * Get current pool size and stats.
     *
     * @return array Associative array with 'capacity', 'available', 'created', and 'in_use' keys.
     */
    public function stats(): array
    {
        $stats = [
            'capacity'   => $this->channel->capacity,
            'available'  => $this->channel->length(),
            'created'    => $this->created,
            'in_use'     => $this->created - $this->channel->length(),
        ];
        // error_log(sprintf('[%s] RedisPool stats: %s', date('Y-m-d H:i:s'), json_encode($stats)));
        return $stats;
    }
}
