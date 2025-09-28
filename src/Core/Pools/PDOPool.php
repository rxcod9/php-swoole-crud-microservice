<?php

declare(strict_types=1);

namespace App\Core\Pools;

use App\Exceptions\PdoConnectionException;
use App\Exceptions\PdoPoolExhaustedException;
use App\Exceptions\PdoPoolNotInitializedException;
use App\Exceptions\QueryFailedException;
use PDO;
use PDOException;
use RuntimeException;

use function sprintf;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Class PDOPool
 *
 * Coroutine-safe PDO connection pool for MySQL.
 * Automatically scales pool size based on usage.
 *
 * @package App\Core\Pools
 */
final class PDOPool
{
    private Channel $channel;          // Channel for managing PDO connections
    private int $min;                  // Minimum connections to keep
    private int $max;                  // Maximum connections allowed
    private array $conf;               // PDO/MySQL connection configuration
    private int $created      = 0;          // Total connections created
    private bool $initialized = false; // Pool initialization flag
    private float $idleBuffer;         // Idle buffer ratio (0-1)
    private float $margin;             // Scaling margin (0-1)

    /**
     * PDOPool constructor.
     *
     * @param array $conf MySQL connection config: host, port, user, pass, db, charset, timeout
     * @param int $min Minimum connections to pre-create
     * @param int $max Maximum connections allowed
     * @param float $idleBuffer Idle buffer ratio (0-1)
     * @param float $margin Scaling margin (0-1)
     */
    public function __construct(
        array $conf,
        int $min = 5,
        int $max = 50,
        float $idleBuffer = 0.05,
        float $margin = 0.05
    ) {
        $this->conf       = $conf;
        $this->min        = $min;
        $this->max        = $max;
        $this->idleBuffer = $idleBuffer;
        $this->margin     = $margin;

        $this->channel = new Channel($max);
    }

    /**
     * Initialize pool inside a coroutine (e.g. from onWorkerStart).
     */
    public function init(): void
    {
        // Pre-create minimum connections to avoid startup delays
        for ($i = 0; $i < $this->min; $i++) {
            $conn = $this->make();

            error_log(sprintf('Pre-created PDO connection #%d', $i + 1));
            $this->channel->push($conn);
        }

        $this->initialized = true;
        error_log(sprintf('PDOPool initialized with min=%d, max=%d', $this->min, $this->max));
    }

    /**
     * Create a new PDO connection.
     *
     * @param int $retry Current retry attempt count.
     * @throws RuntimeException
     */
    private function make(int $retry = 0): PDO
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

            $this->created++;
            error_log(sprintf(
                '[CREATE] PDO connection created. Total connections: %d',
                $this->created
            ));
            return $pdo;
        } catch (PDOException $e) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldPDORetry($e)) {
                // Retry with exponential backoff
                $retry++;
                if ($retry <= 5) {
                    $backoff = (1 << $retry) * 100000; // microseconds
                    error_log(sprintf('[RETRY] Retrying PDO connection in %.2f seconds...', $backoff / 1000000));
                    Coroutine::sleep($backoff / 1000000);
                    return $this->make($retry);
                }
            }

            // If all retries fail, throw exception
            error_log(sprintf(
                '[EXCEPTION] PDO connection error: %s | Connections created: %d',
                $e->getMessage(),
                $this->created
            ));
            throw new PdoConnectionException("PDO connection error: {$e->getMessage()} | Connections created: {$this->created}");
        }
    }

    /**
     * Get a PDO connection from the pool.
     *
     * @param float $timeout Timeout in seconds to wait for a connection
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
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
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
     *
     */
    public function put(PDO $conn): void
    {
        if (!$this->channel->isFull()) {
            $this->channel->push($conn);
            error_log(sprintf('[PUT] PDO connection returned to pool'));
        } else {
            // Pool full, let garbage collector close the PDO object
            unset($conn);
            $conn = null;
            $this->created--;
            error_log(sprintf('[PUT] Pool full, PDO connection discarded. Total connections: %d', $this->created));
        }
    }

    /**
     * Execute a query safely with optional parameters.
     *
     * @param string $sql SQL query
     * @param array $params Optional bound parameters
     * @return array Result set
     */
    public function query(string $sql, array $params = []): array
    {
        $conn = $this->get();
        defer(fn () => $this->put($conn));

        try {
            if ($params) {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } else {
                $stmt = $conn->query($sql);
                return $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            error_log('Exception: ' . $e->getMessage() . PHP_EOL . 'Query: ' . $sql . PHP_EOL); // logged internally
            throw new QueryFailedException("Query failed: {$e->getMessage()}");
        }
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
        $available = $this->channel->length();
        // $used      = max(0, $this->created - $available);

        $idleBufferCount = (int)($this->max * $this->idleBuffer);
        $upperThreshold  = (int)($idleBufferCount * (1 + $this->margin));
        $lowerThreshold  = min($this->min, (int)($idleBufferCount * (1 - $this->margin)));

        // Scale UP
        if ($available < $lowerThreshold && $this->created < $this->max) {
            $toCreate = min($this->max - $this->created, $lowerThreshold - $available);
            for ($i = 0; $i < $toCreate; $i++) {
                $this->channel->push($this->make());
            }
            error_log(sprintf('[SCALE-UP PDO] Created %d connections', $toCreate));
        }

        // Scale DOWN
        if ($available > $upperThreshold && $this->created > $this->min) {
            $toClose = min($this->created - $this->min, $available - $upperThreshold);
            for ($i = 0; $i < $toClose; $i++) {
                $conn = $this->channel->pop(0.01);
                if ($conn) {
                    unset($conn);
                    $this->created--;
                }
            }
            error_log(sprintf('[SCALE-DOWN PDO] Closed %d idle connections', $toClose));
        }
    }
}
