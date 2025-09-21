<?php

namespace App\Core;

use Swoole\Http\Server as SwooleHttp;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Table;
use Swoole\Timer;
use App\Middlewares\AuthMiddleware;
use App\Tasks\LoggerTask;

/**
 * Class HttpServer
 *
 * Sets up and manages the Swoole HTTP server, including:
 * - Server initialization with configuration, TLS/HTTP2 support, and worker management.
 * - Shared memory table for worker health monitoring and liveness checks.
 * - Event handlers for server lifecycle: Start, WorkerStart, WorkerStop, WorkerExit, WorkerError.
 * - Per-worker initialization of MySQL and Redis connection pools.
 * - HTTP request handling with:
 *   - CORS headers and preflight OPTIONS support.
 *   - Worker readiness checks before processing requests.
 *   - Dependency injection container for request-scoped services.
 *   - Authentication middleware.
 *   - Health check endpoint (/healthz) reporting worker and pool status.
 *   - Routing and dispatching to controllers/actions.
 *   - Metrics collection (request count and latency).
 *   - Asynchronous logging via Swoole task workers.
 *   - Graceful resource cleanup after each request.
 * - Task event handler for background jobs (e.g., logging).
 *
 * Usage:
 *   $server = new HttpServer($config, $router);
 *   $server->start();
 *
 * @package App\Core
 */
final class HttpServer
{
    /**
     * @var SwooleHttp Swoole HTTP server instance
     */
    private SwooleHttp $http;

    /**
     * @var Router Router instance for request routing
     */
    private Router $router;

    /**
     * @var array Server configuration
     */
    private array $config;

    /**
     * @var MySQLPool MySQL connection pool
     */
    private MySQLPool $mysql;

    /**
     * @var RedisPool Redis connection pool
     */
    private RedisPool $redis;

    /**
     * @var Table Shared memory table for worker health
     */
    private Table $table;

    /**
     * HttpServer constructor.
     *
     * Initializes the Swoole HTTP server, sets up event handlers, shared memory table,
     * connection pools, and request handling logic.
     *
     * @param array $config Server configuration array
     * @param Router $router Router instance for HTTP request routing
     */
    public function __construct(array $config, Router $router)
    {
        $this->config = $config;
        $this->router = $router;

        $host = $config['server']['host'];
        $port = $config['server']['http_port'];

        // Shared memory table for worker health
        $table = new Table(64);
        $table->column("pid", Table::TYPE_INT, 8);
        $table->column("first_heartbeat", Table::TYPE_INT, 8);
        $table->column("last_heartbeat", Table::TYPE_INT, 8);
        $table->column("mysql_capacity", Table::TYPE_INT, 8);
        $table->column("mysql_available", Table::TYPE_INT, 8);
        $table->column("mysql_created", Table::TYPE_INT, 8);
        $table->column("mysql_in_use", Table::TYPE_INT, 8);
        $table->column("redis_capacity", Table::TYPE_INT, 8);
        $table->column("redis_available", Table::TYPE_INT, 8);
        $table->column("redis_created", Table::TYPE_INT, 8);
        $table->column("redis_in_use", Table::TYPE_INT, 8);
        $table->create();
        $this->table = $table;

        $this->http = new SwooleHttp(
            $host,
            $port,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP | ((($config['server']['ssl']['enable'] ?? false) === true) ? SWOOLE_SSL : 0)
        );

        // Optional TLS/HTTP2 support
        if (($config['server']['ssl']['enable'] ?? false) === true) {
            $this->http->set([
                'ssl_cert_file' => $config['server']['ssl']['cert_file'],
                'ssl_key_file' => $config['server']['ssl']['key_file'],
                'open_http2_protocol' => true, // optional
            ]);
        }

        $this->http->set($config['server']['settings'] ?? []);

        // Server start event
        $this->http->on('Start', fn() => print("HTTP listening on {$host}:{$port}\n"));

        // Worker start event
        $this->http->on('WorkerStart', function (SwooleHttp $server, $workerId) use ($config, $table) {
            $pid = posix_getpid();
            echo "Worker {$workerId} started with {$pid}\n";

            // Write initial health
            $table->set($workerId, [
                "pid" => $pid,
                "first_heartbeat" => time(),
                "last_heartbeat" => time()
            ]);

            // Initialize pools per worker
            $dbConf = $config['db']['mysql'];
            $this->mysql = new MySQLPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);
            $redisConf = $config['redis'];
            $this->redis = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);
            AppContext::setWorkerReady(true);
            echo "Worker {$workerId} started with {$pid} ready\n";

            // Heartbeat every 5s
            Timer::tick(5000, function ($timerId) use ($server, $workerId, $pid, $table) {
                try {
                    $this->mysql->autoScale();
                } catch (\Throwable $e) {
                    echo "[Worker {$workerId}] MySQL autoScale error: " . $e->getMessage() . "\n";
                }

                try {
                    $this->redis->autoScale();
                } catch (\Throwable $e) {
                    echo "[Worker {$workerId}] Redis autoScale error: " . $e->getMessage() . "\n";
                }

                $mysqlStats = $this->mysql->stats();
                $mysqlCapacity  = $mysqlStats['capacity'];
                $mysqlAvailable = $mysqlStats['available'];
                $mysqlCreated   = $mysqlStats['created'];
                $mysqlInUse     = $mysqlStats['in_use'];

                $redisStats = $this->redis->stats();
                $redisCapacity  = $redisStats['capacity'];
                $redisAvailable = $redisStats['available'];
                $redisCreated   = $redisStats['created'];
                $redisInUse     = $redisStats['in_use'];

                $table->set($workerId, [
                    "pid" => $pid,
                    "last_heartbeat" => time(),
                    "mysql_capacity" => $mysqlCapacity,
                    "mysql_available" => $mysqlAvailable,
                    "mysql_created" => $mysqlCreated,
                    "mysql_in_use" => $mysqlInUse,
                    "redis_capacity" => $redisCapacity,
                    "redis_available" => $redisAvailable,
                    "redis_created" => $redisCreated,
                    "redis_in_use" => $redisInUse,
                ]);
            });
        });

        // Worker stop event (graceful)
        $this->http->on('WorkerStop', function (SwooleHttp $server, int $workerId) {
            echo "[WorkerStop] Worker #{$workerId} stopped\n";
            AppContext::setWorkerReady(false);
            $this->table->del((string)$workerId);
        });

        // Worker exit event (after WorkerStop)
        $this->http->on('WorkerExit', function (SwooleHttp $server, int $workerId) {
            echo "[WorkerExit] Worker #{$workerId} exited\n";
            AppContext::setWorkerReady(false);
            $this->table->del((string)$workerId);
        });

        // Worker error event (crash/fatal error)
        $this->http->on('WorkerError', function (SwooleHttp $server, int $workerId, int $workerPid, int $exitCode, int $signal) {
            echo "[WorkerError] Worker #{$workerId} (PID: {$workerPid}) crashed. Exit code: {$exitCode}, Signal: {$signal}\n";
            AppContext::setWorkerReady(false);
            $this->table->del((string)$workerId);
        });

        // HTTP request event
        $this->http->on('request', function (Request $req, Response $res) use ($table) {
            // Always add CORS headers
            $res->header("Access-Control-Allow-Origin", "*");
            $res->header("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
            $res->header("Access-Control-Allow-Headers", "Content-Type, Authorization");

            // Handle preflight OPTIONS requests
            if ($req->server['request_method'] === 'OPTIONS') {
                $res->status(204); // No Content
                $res->end();
                return;
            }

            // Wait until worker is ready
            $waited = 0;
            while (!AppContext::isWorkerReady() && $waited < 2000) {
                usleep(10000); // 10ms
                $waited += 10;
                // After 2 seconds, give up and return 503 to client
                if ($waited >= 2000) {
                    $res->status(503);
                    $res->end("Service Unavailable – worker not ready");
                    return;
                }
            }

            $container = new Container();
            $reqId = bin2hex(random_bytes(8));
            $start = microtime(true);

            try {
                // Authentication middleware
                (new AuthMiddleware())->handle($req, $container);

                // Bind request-scoped DbContext (one MySQL and Redis connection per request)
                if (!isset($this->mysql) || !isset($this->redis)) {
                    throw new \RuntimeException('Database pools not initialized after waiting');
                }

                // $conn = $this->mysql->get();
                // $redisConn = $this->redis->get();
                // $container->bind(DbContext::class, fn() => new DbContext($conn));
                $container->bind(MySQLPool::class, fn() => $this->mysql);

                // $container->bind(RedisContext::class, fn() => new RedisContext($redisConn));
                $container->bind(RedisPool::class, fn() => $this->redis);

                // Health check endpoint
                if ($req->server['request_uri'] === '/healthz') {
                    $res->header('Content-Type', 'application/json');
                    $res->status(200);
                    $data = [];
                    foreach ($table as $wid => $row) {
                        $data[$wid] = [
                            ...$row,
                            // "pid" => $row["pid"],
                            // "last_heartbeat" => $row["last_heartbeat"],
                            "alive" => (time() - $row["last_heartbeat"] < 10),
                            "since" => time() - $row["last_heartbeat"] < 10 ? (time() - $row["last_heartbeat"]) . "s" : "dead",
                        ];
                    }

                    // sort by MySQL In Use	and Redis In Use
                    usort($data, function ($a, $b) {
                        if ($a['mysql_in_use'] === $b['mysql_in_use']) {
                            return $b['redis_in_use'] <=> $a['redis_in_use'];
                        }
                        return $b['mysql_in_use'] <=> $a['mysql_in_use'];
                    });

                    $res->end(json_encode([
                        'ok' => true,
                        // 'mysql'  => $this->mysql->stats(),
                        // 'redis'  => $this->redis->stats(),
                        'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
                        'ts' => time(),
                        'pid' => posix_getpid(),
                        'workers_count' => count($data),
                        'workers' => $data,
                    ]));
                    return;
                } elseif ($req->server['request_uri'] === '/healthz.html') {
                    $res->status(200);
                    $data = [];
                    foreach ($table as $wid => $row) {
                        $data[$wid] = [
                            ...$row,
                            // "pid" => $row["pid"],
                            // "last_heartbeat" => $row["last_heartbeat"],
                            "alive" => (time() - $row["last_heartbeat"] < 10),
                            "since" => time() - $row["last_heartbeat"] < 10 ? (time() - $row["last_heartbeat"]) . "s" : "dead"
                        ];
                    }

                    // sort by MySQL In Use	and Redis In Use
                    usort($data, function ($a, $b) {
                        if ($a['mysql_in_use'] === $b['mysql_in_use']) {
                            return $b['redis_in_use'] <=> $a['redis_in_use'];
                        }
                        return $b['mysql_in_use'] <=> $a['mysql_in_use'];
                    });

                    $responseHtml = "<!DOCTYPE html>
                        <html lang=\"en\">
                        <head>
                            <meta charset=\"UTF-8\">
                            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                            <title>Health Check</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                table { border-collapse: collapse; width: 100%; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; }
                                tr:nth-child(even) { background-color: #f9f9f9; }
                                .alive { color: green; font-weight: bold; }
                                .dead { color: red; font-weight: bold; }
                            </style>
                        </head>
                        <body>
                            <h1>Health Check</h1>
                            <p>Uptime: " . (time() - ($_SERVER['REQUEST_TIME'] ?? time())) . " seconds</p>
                            <p>Timestamp: " . date('Y-m-d H:i:s') . "</p>
                            <p>PID: " . posix_getpid() . "</p>
                            <p>Workers Count: " . count($data)  . "</p>
                            <table>
                                <tr>
                                    <th>Worker ID</th>
                                    <th>PID</th>
                                    <th>First Heartbeat</th>
                                    <th>Last Heartbeat</th>
                                    <th>Status</th>
                                    <th>Uptime</th>
                                    <th>MySQL Capacity</th>
                                    <th>MySQL Available</th>
                                    <th>MySQL Created</th>
                                    <th>MySQL In Use</th>
                                    <th>Redis Capacity</th>
                                    <th>Redis Available</th>
                                    <th>Redis Created</th>
                                    <th>Redis In Use</th>
                                </tr>";
                    foreach ($data as $wid => $row) {
                        $statusClass = $row['alive'] ? 'alive' : 'dead';
                        $statusText = $row['alive'] ? 'Alive' : 'Dead';
                        $responseHtml .= "<tr>
                            <td>{$wid}</td>
                            <td>{$row['pid']}</td>
                            <td>" . date('Y-m-d H:i:s', $row['first_heartbeat']) . "</td>
                            <td>" . date('Y-m-d H:i:s', $row['last_heartbeat']) . "</td>
                            <td class=\"{$statusClass}\">{$statusText}</td>
                            <td>" . (time() - ($row['first_heartbeat'] ?? time())) . " seconds</td>
                            <td>{$row['mysql_capacity']}</td>
                            <td>{$row['mysql_available']}</td>
                            <td>{$row['mysql_created']}</td>
                            <td>{$row['mysql_in_use']}</td>
                            <td>{$row['redis_capacity']}</td>
                            <td>{$row['redis_available']}</td>
                            <td>{$row['redis_created']}</td>
                            <td>{$row['redis_in_use']}</td>
                        </tr>";
                    }
                    $responseHtml .= "</table>
                        </body>
                        </html>";
                    $res->end($responseHtml);
                    return;
                }

                // // Metrics collection
                // $reg = Metrics::reg();
                // $counter = $reg->getOrRegisterCounter(
                //     'http_requests_total',
                //     'Requests',
                //     'Total HTTP requests',
                //     ['method', 'path', 'status']
                // );
                // $hist = $reg->getOrRegisterHistogram(
                //     'http_request_seconds',
                //     'Latency',
                //     'HTTP request latency',
                //     ['method', 'path']
                // );

                // Routing and dispatching
                [$action, $params] = $this->router->match($req->server['request_method'], $req->server['request_uri']);
                $payload = (new Dispatcher($container))->dispatch($action, $params, $req);

                $status = $payload['__status'] ?? 200;
                $json = $payload['__json'] ?? null;

                $res->header('Content-Type', 'application/json');
                $res->status($status);
                if ($status === 204) {
                    $res->end();
                } else {
                    $res->end($json ? json_encode($json) : json_encode($payload));
                }

                // Metrics and async logging
                $dur = microtime(true) - $start;
                $path = parse_url($req->server['request_uri'] ?? '/', PHP_URL_PATH);

                // $counter->inc([$req->server['request_method'], $path, (string)$status]);
                // $hist->observe($dur, [$req->server['request_method'], $path]);

                $this->http->task([
                    'type' => 'log',
                    'data' => [
                        'id' => $reqId,
                        'method' => $req->server['request_method'],
                        'path' => $path,
                        'status' => $status,
                        'dur_ms' => (int)round($dur * 1000)
                    ]
                ]);
            } catch (\Throwable $e) {
                $status = $e->getCode() ?: 500;
                $res->header('Content-Type', 'application/json');
                $res->status($status);
                $res->end(json_encode(['error' => $e->getMessage()]));

                $this->http->task([
                    'type' => 'log',
                    'data' => [
                        'error' => $e->getMessage(),
                    ]
                ]);
            } finally {
                // Release MySQL and Redis connections back to pool
                // if (isset($conn)) {
                //     $this->mysql->put($conn);
                // }
                // if (isset($redisConn)) {
                //     $this->redis->put($redisConn);
                // }
            }
        });

        // Task event handler for async jobs (e.g., logging)
        $this->http->on('task', function (SwooleHttp $server, \Swoole\Server\Task $task) {
            $data = $task->data;
            if (($data['type'] ?? '') === 'log') {
                LoggerTask::handle($data['data']);
            }
            return true;
        });

        // Task finish event (no-op)
        $this->http->on('finish', fn() => null);
    }

    /**
     * Start the HTTP server.
     *
     * Boots the Swoole HTTP server and begins accepting requests.
     *
     * @return void
     */
    public function start(): void
    {
        $this->http->start();
    }
}
