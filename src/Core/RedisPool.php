<?php

namespace App\Core;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Redis;

/**
 * RedisPool
 * 
 * Coroutine-safe Redis connection pool with auto-scaling.
 * Handles connection creation, scaling, health checks, and command execution.
 */
final class RedisPool
{
    private Channel $chan;
    private int $min;
    private int $max;
    private array $conf;
    private int $created = 0;
    private bool $initialized = false;

    /**
     * Constructor
     * @param array $conf Redis connection config
     * @param int $min Minimum connections
     * @param int $max Maximum connections
     */
    public function __construct(array $conf, int $min = 5, int $max = 200)
    {
        $this->conf = $conf;
        $this->min = $min;
        $this->max = $max;

        $this->chan = new Channel($max);

        // Pre-create minimum connections
        for ($i = 0; $i < $min; $i++) {
            $conn = $this->make();
            error_log(sprintf('[%s] Pre-created Redis connection #%d', date('Y-m-d H:i:s'), $i + 1));
            $this->chan->push($conn);
        }
        $this->initialized = true;
        error_log(sprintf('[%s] RedisPool initialized with min=%d, max=%d', date('Y-m-d H:i:s'), $min, $max));
    }

    /**
     * Create a new Redis connection
     * @return Redis
     * @throws \RuntimeException
     */
    private function make(): Redis
    {
        try {
            $redis = new Redis();
            $connected = $redis->connect($this->conf['host'] ?? '127.0.0.1', $this->conf['port'] ?? 6379);

            // password authentication if needed
            if ($connected && !empty($this->conf['password'])) {
                $redis->auth($this->conf['password']);
            }
            // select database if specified
            if ($connected && isset($this->conf['database'])) {
                $redis->select($this->conf['database']);
            }

            // check connection
            if (!$connected) {
                error_log(sprintf('[%s] ERROR Redis connection failed: %s | Pool created: %d', date('Y-m-d H:i:s'), $redis->connect_error, $this->created));
                throw new \RuntimeException("Redis connection failed: {$redis->connect_error} | Pool created: {$this->created}");
            }

            $this->created++;
            // log count of created connections
            error_log(sprintf(
                '[%s] Redis connection created. Total connections: %d',
                date('Y-m-d H:i:s'),
                $this->created
            ));
            return $redis;
        } catch (\Throwable $e) {
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
     * Get a Redis connection from the pool
     * Auto-scales pool size based on usage patterns
     *
     * @param float $timeout Timeout in seconds to wait for a connection
     * @throws \RuntimeException if pool is exhausted
     * @return Redis
     */
    public function get(float $timeout = 1.0): Redis
    {
        if (!$this->initialized) {
            throw new \RuntimeException('MySQL pool not initialized yet');
        }
        $available = $this->chan->length();
        $used = $this->created - $available;
        $exhaustedRatio = $used / $this->max;

        // --- Auto-scale up ---
        if (($available <= 1 || $exhaustedRatio >= 0.75) && $this->created < $this->max) {
            $toCreate = min($this->max - $this->created, max(1, (int)($this->max * 0.05)));
            error_log(sprintf('[%s] Auto-scaling UP: Creating %d new Redis connections', date('Y-m-d H:i:s'), $toCreate));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->chan->push($this->make());
            }
        }

        // --- Auto-scale down ---
        $idleRatio = $available / max(1, $this->created);
        if ($idleRatio >= 0.5 && $this->created > $this->min) {
            // Close up to 25% of excess idle connections
            $toClose = min($this->created - $this->min, (int)($this->created * 0.05));
            error_log(sprintf('[%s] Auto-scaling DOWN: Closing %d idle Redis connections', date('Y-m-d H:i:s'), $toClose));
            for ($i = 0; $i < $toClose; $i++) {
                $conn = $this->chan->pop(0.01); // non-blocking pop
                if ($conn) {
                    $conn->close();
                    $this->created--;
                    error_log(sprintf('[%s] Closed idle Redis connection. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
                }
            }
        }

        $conn = $this->chan->pop($timeout);
        if (!$conn) {
            error_log(sprintf('[%s] ERROR Redis pool exhausted (timeout=%.2f)', date('Y-m-d H:i:s'), $timeout));
            throw new \RuntimeException('Redis pool exhausted', 503);
        }

        // Health check: set/get/del a random key
        $key = '__redis_pool_health_' . bin2hex(random_bytes(8));
        $value = bin2hex(random_bytes(16));

        // Try set
        $setResult = $conn->set($key, $value);
        if ($setResult !== true) {
            error_log(sprintf('[%s] WARNING: Redis SET health check failed. Key: %s', date('Y-m-d H:i:s'), $key));
            $conn = $this->make();
        } else {
            // Try get
            $getResult = $conn->get($key);
            if ($getResult !== $value) {
                error_log(sprintf('[%s] WARNING: Redis GET health check failed. Key: %s', date('Y-m-d H:i:s'), $key));
                $conn = $this->make();
            }
            // Try delete
            $conn->del($key);
            error_log(sprintf('[%s] Redis connection health check passed (set/get/del)', date('Y-m-d H:i:s')));
        }
        // $pingResult = $conn->ping("PONG");
        // error_log(sprintf('[%s] Redis ping result: %s (type: %s)', date('Y-m-d H:i:s'), var_export($pingResult, true), gettype($pingResult)));
        // error_log(sprintf('[%s] Redis errCode: %d, errMsg: %s', date('Y-m-d H:i:s'), $conn->errCode, $conn->errMsg));
        // if ($pingResult !== 'PONG') {
        //     error_log(sprintf('[%s] WARNING: Redis ping did not return PONG. Actual: %s', date('Y-m-d H:i:s'), var_export($pingResult, true)));
        // }
        // // Ping to check health
        // if (!$conn->ping("PONG")) {
        //     error_log(sprintf('[%s] WARNING Redis connection unhealthy, recreating... errCode: %d, errMsg: %s', date('Y-m-d H:i:s'), $conn->errCode, $conn->errMsg));
        //     $conn = $this->make();
        // } else {
        //     error_log(sprintf('[%s] Redis connection acquired from pool', date('Y-m-d H:i:s')));
        // }

        return $conn;
    }

    /**
     * Return a Redis connection back to the pool
     *
     * @param Redis $conn The Redis connection to return
     */
    public function put(Redis $conn): void
    {
        if (!$this->chan->isFull()) {
            $this->chan->push($conn);
            error_log(sprintf('[%s] Redis connection returned to pool', date('Y-m-d H:i:s')));
        } else {
            // Pool is full, close the connection
            $conn->close();
            $this->created--;
            error_log(sprintf('[%s] Pool full, closed Redis connection. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
        }
    }

    /**
     * Execute a Redis command
     *
     * Example:
     *   $redis->command('set', ['foo', 'bar']);
     *   $val = $redis->command('get', ['foo']);
     *
     * @param string $cmd Redis command
     * @param array $args Command arguments
     * @return mixed
     * @throws \RuntimeException
     */
    public function command(string $cmd, array $args = []): mixed
    {
        $conn = $this->get();

        try {
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
            $result = $conn->{$cmd}(...$args);
        } finally {
            $this->put($conn);
        }

        return $result;
    }

    /**
     * Get current pool size and stats
     *
     * @return array Associative array with 'capacity', 'available', 'created', and 'in_use' keys
     */
    public function stats(): array
    {
        $stats = [
            'capacity'   => $this->chan->capacity,
            'available'  => $this->chan->length(),
            'created'    => $this->created,
            'in_use'     => $this->created - $this->chan->length(),
        ];
        error_log(sprintf('[%s] RedisPool stats: %s', date('Y-m-d H:i:s'), json_encode($stats)));
        return $stats;
    }
}

