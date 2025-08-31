<?php

namespace App\Core;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Mysql;

/**
 * MySQLPool
 * 
 * A coroutine-safe MySQL connection pool for Swoole.
 * Automatically scales pool size based on usage patterns.
 */
final class MySQLPool
{
    private Channel $chan;
    private int $min;
    private int $max;
    private array $conf;
    private int $created = 0;
    private bool $initialized = false;

    /**
     * Constructor
     * @param array $conf MySQL connection config
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
            error_log(sprintf('[%s] [INIT] Pre-created connection #%d', date('Y-m-d H:i:s'), $i + 1));
            $this->chan->push($conn);
        }
        $this->initialized = true;
        error_log(sprintf('[%s] [INIT] MySQL Pool initialized with %d connections', date('Y-m-d H:i:s'), $min));
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Create a new MySQL connection
     */
    private function make(): Mysql
    {
        try {
            $mysql = new Mysql();
            $ok = $mysql->connect([
                'host' => $this->conf['host'] ?? '127.0.0.1',
                'port' => $this->conf['port'] ?? 3306,
                'user' => $this->conf['user'] ?? 'root',
                'password' => $this->conf['pass'] ?? '',
                'database' => $this->conf['db'] ?? '',
                'charset' => $this->conf['charset'] ?? 'utf8mb4',
                'timeout' => $this->conf['timeout'] ?? 2,
            ]);

            if ($ok === false) {
                error_log(sprintf('[%s] [ERROR] MySQL connection failed: %s | MySQL Pool created: %d', date('Y-m-d H:i:s'), $mysql->connect_error, $this->created));
                throw new \RuntimeException("MySQL connection failed: {$mysql->connect_error} | MySQL Pool created: {$this->created}");
            }

            $this->created++;
            // log count of created connections
            error_log(sprintf(
                '[%s] [CREATE] MySQL connection created. Total connections: %d',
                date('Y-m-d H:i:s'),
                $this->created
            ));
            return $mysql;
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[%s] [EXCEPTION] MySQL connection error: %s | MySQL Pool created: %d',
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $this->created
            ));
            throw new \RuntimeException("MySQL connection error: {$e->getMessage()} | MySQL Pool created: {$this->created}");
        }
    }

    /**
     * Get a MySQL connection from the pool
     * Auto-scales pool size based on usage patterns
     *
     * @param float $timeout Timeout in seconds to wait for a connection
     * @throws \RuntimeException if pool is exhausted
     */
    public function get(float $timeout = 1.0): Mysql
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
            error_log(sprintf('[%s] [SCALE-UP Mysql] Creating %d new connections (used: %d, available: %d)', date('Y-m-d H:i:s'), $toCreate, $used, $available));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->chan->push($this->make());
            }
        }

        // --- Auto-scale down ---
        $idleRatio = $available / max(1, $this->created);
        if ($idleRatio >= 0.5 && $this->created > $this->min) {
            // Close up to 25% of excess idle connections
            $toClose = min($this->created - $this->min, (int)($this->created * 0.05));
            error_log(sprintf('[%s] [SCALE-DOWN Mysql] Closing %d idle connections (idleRatio: %.2f)', date('Y-m-d H:i:s'), $toClose, $idleRatio));
            for ($i = 0; $i < $toClose; $i++) {
                $conn = $this->chan->pop(0.01); // non-blocking pop
                if ($conn) {
                    $conn->close();
                    $this->created--;
                    error_log(sprintf('[%s] [CLOSE] MySQL Connection closed. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
                }
            }
        }

        $conn = $this->chan->pop($timeout);
        if (!$conn) {
            error_log(sprintf('[%s] [ERROR] DB pool exhausted (timeout: %.2f)', date('Y-m-d H:i:s'), $timeout));
            throw new \RuntimeException('DB pool exhausted', 503);
        }

        // Ping to check health
        if ($conn->query('SELECT 1') === false) {
            error_log(sprintf('[%s] [PING-FAIL] MySQL Connection unhealthy, recreating...', date('Y-m-d H:i:s')));
            $conn = $this->make();
        } else {
            error_log(sprintf('[%s] [PING] MySQL Connection healthy', date('Y-m-d H:i:s')));
        }

        return $conn;
    }

    /**
     * Return a MySQL connection back to the pool
     * 
     * @param MySQL $conn The MySQL connection to return
     */
    public function put(Mysql $conn): void
    {
        if (!$this->chan->isFull()) {
            $this->chan->push($conn);
            error_log(sprintf('[%s] [PUT] MySQL Connection returned to pool', date('Y-m-d H:i:s')));
        } else {
            // MySQL Pool is full, close the connection
            $conn->close();
            $this->created--;
            error_log(sprintf('[%s] [PUT] MySQL Pool full, connection closed. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
        }
    }

    /**
     * Execute a query safely with optional parameters
     *
     * @param string $sql The SQL query to execute
     * @param array $params Optional parameters for prepared statements
     * @return array The result set as an array
     */
    public function query(string $sql, array $params = []): array
    {
        $conn = $this->get();
        try {
            if ($params) {
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    error_log(sprintf('[%s] [ERROR] Prepare failed: %s', date('Y-m-d H:i:s'), $conn->error));
                    throw new \RuntimeException('Prepare failed: ' . $conn->error);
                }
                $result = $stmt->execute($params);
                error_log(sprintf('[%s] [QUERY] Executed prepared statement: %s', date('Y-m-d H:i:s'), $sql));
            } else {
                $result = $conn->query($sql);
                error_log(sprintf('[%s] [QUERY] Executed query: %s', date('Y-m-d H:i:s'), $sql));
            }
        } finally {
            $this->put($conn);
        }

        return is_array($result) ? $result : [];
    }

    /**
     * Get current pool size and stats
     *
     * @return array Associative array with 'size', 'available', and 'created' keys
     */
    public function stats(): array
    {
        $stats = [
            'capacity'   => $this->chan->capacity,
            'available'  => $this->chan->length(),
            'created'    => $this->created,
            'in_use'     => $this->created - $this->chan->length(),
        ];
        error_log(sprintf('[%s] [STATS] MySQL Pool stats: %s', date('Y-m-d H:i:s'), json_encode($stats)));
        return $stats;
    }
}
