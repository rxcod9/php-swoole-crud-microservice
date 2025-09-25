<?php

namespace App\Core\Pools;

use RuntimeException;
use Swoole\Coroutine\Channel;

/**
 * Class MySQLPool
 *
 * A coroutine-safe MySQL connection pool for Swoole.
 * Automatically scales pool size based on usage patterns.
 *
 * @package App\Core
 */
final class MySQLPool
{
    /**
     * @var Channel The coroutine channel used for managing Redis connections.
     */
    private Channel $channel;

    /**
     * @var int Minimum number of connections to keep in the pool.
     */
    private int $min;

    /**
     * @var int Maximum number of connections allowed in the pool.
     */
    private int $max;

    /**
     * @var array MySQL connection configuration.
     */
    private array $conf;

    /**
     * @var int Number of created connections.
     */
    private int $created = 0;

    /**
     * @var bool Whether the pool has been initialized.
     */
    private bool $initialized = false;

    /**
     * @var float Idle buffer ratio for scaling decisions (0 to 1).
     */
    private float $idleBuffer; // e.g., 0.05 = 5%

    /**
     * @var float Margin ratio for scaling decisions (0 to 1).
     */
    private float $margin;     // e.g., 0.05 = 5% margin

    /**
     * MySQLPool constructor.
     *
     * @param array $conf MySQL connection config (host, port, user, pass, db, charset, timeout)
     * @param int   $min Minimum connections to pre-create
     * @param int   $max Maximum connections allowed
     */
    public function __construct(
        array $conf,
        int $min = 5,
        int $max = 200,
        float $idleBuffer = 0.05,
        float $margin = 0.05
    ) {
        $this->conf = $conf;
        $this->min = $min;
        $this->max = $max;
        $this->idleBuffer = $idleBuffer;
        $this->margin = $margin;

        $this->channel = new Channel($max);

        // Pre-create minimum connections
        for ($i = 0; $i < $min; $i++) {
            $conn = $this->make();
            error_log(sprintf('[%s] [INIT] Pre-created connection #%d', date('Y-m-d H:i:s'), $i + 1));
            $this->channel->push($conn);
        }
        $this->initialized = true;
        error_log(sprintf('[%s] [INIT] MySQL Pool initialized with %d connections', date('Y-m-d H:i:s'), $min));
    }

    /**
     * Create a new MySQL connection.
     * Uses exponential backoff for retries on failure.
     *
     * @param int $retry Current retry attempt count.
     *
     * @return MySQL
     * @throws \RuntimeException If connection fails.
     */
    private function make(int $retry = 0): MySQL
    {
        try {
            $mysql = new MySQL();
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
                throw new RuntimeException("MySQL connection failed: {$mysql->connect_error} | MySQL Pool created: {$this->created}");
            }

            $this->created++;
            error_log(sprintf(
                '[%s] [CREATE] MySQL connection created. Total connections: %d',
                date('Y-m-d H:i:s'),
                $this->created
            ));
            return $mysql;
        } catch (\Throwable $e) {
            // retry with exponential backoff
            // if ($retry <= 10) {
            //     $backoff = (1 << $retry) * 100000; // exponential backoff in microseconds
            //     error_log(sprintf('[%s] [RETRY] Retrying MySQL connection in %.2f seconds...', date('Y-m-d H:i:s'), $backoff / 1000000));
            //     Coroutine::sleep($backoff / 1000000);
            //     $retry++;
            //     return $this->make($retry);
            // }
            // after retries, throw exception
            // give up and let the caller handle it

            error_log(sprintf(
                '[%s] [EXCEPTION] MySQL connection error: %s | MySQL Pool created: %d',
                date('Y-m-d H:i:s'),
                $e->getMessage(),
                $this->created
            ));
            throw new RuntimeException("MySQL connection error: {$e->getMessage()} | MySQL Pool created: {$this->created}");
        }
    }

    /**
     * Get a MySQL connection from the pool.
     * Auto-scales pool size based on usage patterns.
     *
     * @param float $timeout Timeout in seconds to wait for a connection.
     * @return MySQL
     * @throws \RuntimeException If pool is exhausted or not initialized.
     */
    public function get(float $timeout = 1.0): MySQL
    {
        if (!$this->initialized) {
            throw new RuntimeException('MySQL pool not initialized yet');
        }

        $available = $this->channel->length();
        $used = $this->created - $available;

        // Auto-scale up
        if (($available <= 1) && $this->created < $this->max) {
            $toCreate = 1; //min($this->max - $this->created, max(1, (int)($this->max * 0.05)));
            error_log(sprintf('[%s] [SCALE-UP MySQL] Creating %d new connections (used: %d, available: %d)', date('Y-m-d H:i:s'), $toCreate, $used, $available));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
        }

        $conn = $this->channel->pop($timeout);
        if (!$conn) {
            error_log(sprintf('[%s] [ERROR] DB pool exhausted (timeout=%.2f, available=%d, used=%d, created=%d)', date('Y-m-d H:i:s'), $timeout, $available, $used, $this->created));
            throw new RuntimeException('DB pool exhausted', 503);
        }

        // // Ping to check health
        // if ($conn->query('SELECT 1') === false) {
        //     error_log(sprintf('[%s] [PING-FAIL] MySQL Connection unhealthy, recreating...', date('Y-m-d H:i:s')));
        //     $conn = $this->make();
        // } else {
        //     error_log(sprintf('[%s] [PING] MySQL Connection healthy', date('Y-m-d H:i:s')));
        // }

        return $conn;
    }

    /**
     * Return a MySQL connection back to the pool.
     *
     * @param MySQL $conn The MySQL connection to return.
     * @return void
     */
    public function put(MySQL $conn): void
    {
        if (!$this->channel->isFull()) {
            $this->channel->push($conn);
            error_log(sprintf('[%s] [PUT] MySQL Connection returned to pool', date('Y-m-d H:i:s')));
        } else {
            // MySQL Pool is full, close the connection
            $conn->close();
            $this->created--;
            error_log(sprintf('[%s] [PUT] MySQL Pool full, connection closed. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
        }
    }

    /**
     * Execute a query safely with optional parameters.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters for prepared statements.
     * @return array The result set as an array.
     * @throws \RuntimeException If query or prepare fails.
     */
    public function query(string $sql, array $params = []): array
    {
        $conn = $this->get();
        defer(fn() => isset($conn) && $conn->connected && $this->put($conn));

        if ($params) {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log(sprintf('[%s] [ERROR] Prepare failed: %s', date('Y-m-d H:i:s'), $conn->error));
                throw new RuntimeException('Prepare failed: ' . $conn->error);
            }
            $result = $stmt->execute($params);
            error_log(sprintf('[%s] [QUERY] Executed prepared statement: %s', date('Y-m-d H:i:s'), $sql));
        } else {
            $result = $conn->query($sql);
            error_log(sprintf('[%s] [QUERY] Executed query: %s', date('Y-m-d H:i:s'), $sql));
        }

        return is_array($result) ? $result : [];
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
        // error_log(sprintf('[%s] [STATS] MySQL Pool stats: %s', date('Y-m-d H:i:s'), json_encode($stats)));
        return $stats;
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
            error_log(sprintf('[%s] [SCALE-UP MySQL] Creating %d new connections (used: %d, available: %d)', date('Y-m-d H:i:s'), $toCreate, $used, $available));
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
        }

        // ----------- Scale DOWN if idle connections exceed upperThreshold -----------
        if ($available > $upperThreshold && $this->created > $this->min) {
            $excessIdle = $available - $upperThreshold;
            $toClose = min($this->created - $this->min, $excessIdle);
            error_log(sprintf('[%s] [SCALE-DOWN MySQL] Auto-scaling DOWN: Closing %d idle Redis connections', date('Y-m-d H:i:s'), $toClose));
            for ($i = 0; $i < $toClose; $i++) {
                $conn = $this->channel->pop(0.01); // non-blocking pop
                if ($conn) {
                    $conn->close();
                    $this->created--;
                    error_log(sprintf('[%s] [CLOSE] MySQL Connection closed. Total connections: %d', date('Y-m-d H:i:s'), $this->created));
                }
            }
        }
    }
}
