<?php

namespace App\Core;

use Swoole\Http\Server as SwooleHttp;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Table;
use Swoole\Timer;
use App\Middlewares\AuthMiddleware;
use App\Tasks\LoggerTask;

final class HttpServer
{
    private SwooleHttp $http;
    private Router $router;
    private array $config;
    private MySQLPool $mysql;
    private RedisPool $redis;
    private Table $table;

    public function __construct(array $config, Router $router)
    {
        $this->config = $config;
        $this->router = $router;

        $host = $config['server']['host'];
        $port = $config['server']['http_port'];

        // Shared memory table for worker health
        $table = new Table(64);
        $table->column("pid", Table::TYPE_INT, 8);
        $table->column("last_heartbeat", Table::TYPE_INT, 8);
        $table->create();

        $this->http = new SwooleHttp($host, $port);
        $this->http->set($config['server']['settings'] ?? []);

        // optional TLS/HTTP2
        if (($config['server']['ssl']['enable'] ?? false) === true) {
            $this->http->set([
                'ssl_cert_file' => $config['server']['ssl']['cert_file'],
                'ssl_key_file' => $config['server']['ssl']['key_file'],
            ]);
        }

        $this->http->on('Start', fn() => print("HTTP listening on {$host}:{$port}\n"));

        $this->http->on('WorkerStart', function (SwooleHttp $server, $workerId) use ($config, $table) {
            $pid = posix_getpid();
            echo "Worker {$workerId} started with {$pid}\n";

            // Write initial health
            $table->set($workerId, [
                "pid" => $pid,
                "last_heartbeat" => time()
            ]);

            // heartbeat every 5s
            Timer::tick(5000, function ($timerId) use ($server, $workerId, $pid, $table) {
                $table->set($workerId, [
                    "pid" => $pid,
                    "last_heartbeat" => time()
                ]);
            });

            // Initialize pools per worker
            $dbConf = $config['db']['mysql'];
            $this->mysql = new MySQLPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);
            $redisConf = $config['redis'];
            $this->redis = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);
            AppContext::setWorkerReady(true);
            echo "Worker {$workerId} started with {$pid} ready\n";
        });

        // WorkerStop (called when worker is stopping gracefully)
        $this->http->on('WorkerStop', function (SwooleHttp $server, int $workerId) {
            echo "[WorkerStop] Worker #{$workerId} stopped\n";
            AppContext::setWorkerReady(false);
            $this->table->del((string)$workerId);
        });

        // WorkerExit (triggered when worker exits, after WorkerStop)
        $this->http->on('WorkerExit', function (SwooleHttp $server, int $workerId) {
            echo "[WorkerExit] Worker #{$workerId} exited\n";
            AppContext::setWorkerReady(false);
            $this->table->del((string)$workerId);
        });

        // WorkerError (called when worker process crashes/fatal error)
        $this->http->on('WorkerError', function (SwooleHttp $server, int $workerId, int $workerPid, int $exitCode, int $signal) {
            echo "[WorkerError] Worker #{$workerId} (PID: {$workerPid}) crashed. Exit code: {$exitCode}, Signal: {$signal}\n";
            AppContext::setWorkerReady(false);
            $this->table->del((string)$workerId);
        });

        $this->http->on('request', function (Request $req, Response $res) use ($table) { // ✅ Always add CORS headers
            $res->header("Access-Control-Allow-Origin", "*");
            $res->header("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
            $res->header("Access-Control-Allow-Headers", "Content-Type, Authorization");

            // ✅ Handle preflight OPTIONS requests
            if ($req->server['request_method'] === 'OPTIONS') {
                $res->status(204); // No Content
                $res->end();
                return;
            }

            // ini_set('display_errors', 1);
            // ini_set('display_startup_errors', 1);
            // error_reporting(E_ALL);

            // wait until ready 
            $waited = 0;
            while (!AppContext::isWorkerReady() && $waited < 2000) {
                usleep(10000); // 10ms
                $waited += 10;
                # After 2 seconds, give up and return 503 to client
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
                // Auth
                (new AuthMiddleware())->handle($req, $container);

                // Bind request-scoped DbContext (one MySQL and redis connection per request)
                if (!isset($this->mysql) || !isset($this->redis)) {
                    throw new \RuntimeException('Database pools not initialized after waiting');
                }

                $conn = $this->mysql->get();
                $redisConn = $this->redis->get();
                $container->bind(DbContext::class, fn() => new DbContext($conn));
                $container->bind(MySQLPool::class, fn() => $this->mysql);

                $container->bind(RedisContext::class, fn() => new RedisContext($redisConn));
                $container->bind(RedisPool::class, fn() => $this->redis);

                if ($req->server['request_uri'] === '/healthz') {
                    $res->header('Content-Type', 'application/json');
                    $res->status(200);
                    $data = [];
                    foreach ($table as $wid => $row) {
                        $data[$wid] = [
                            "pid" => $row["pid"],
                            "last_heartbeat" => $row["last_heartbeat"],
                            "alive" => (time() - $row["last_heartbeat"] < 10), // simple liveness check
                            "since" => time() - $row["last_heartbeat"] < 10 ? (time() - $row["last_heartbeat"]) . "s" : "dead"
                        ];
                    }
                    $res->end(json_encode([
                        'ok' => true,
                        'mysql'  => $this->mysql->stats(),
                        'redis'  => $this->redis->stats(),
                        'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
                        'ts' => time(),
                        'pid' => posix_getpid(),
                        'workers_count' => count($data),
                        'workers' => $data,
                    ]));
                    return;
                }

                // Metrics
                $reg = Metrics::reg();
                $counter = $reg->getOrRegisterCounter(
                    'http_requests_total',
                    'Requests',
                    'Total HTTP requests',
                    ['method', 'path', 'status']
                );
                $hist = $reg->getOrRegisterHistogram(
                    'http_request_seconds',
                    'Latency',
                    'HTTP request latency',
                    ['method', 'path']
                );

                // Routing
                // try {
                [$action, $params] = $this->router->match($req->server['request_method'], $req->server['request_uri']);
                $payload = (new Dispatcher($container))->dispatch($action, $params, $req);

                $status = $payload['__status'] ?? 200;
                $json = $payload['__json'] ?? $payload;

                $res->header('Content-Type', 'application/json');
                $res->status($status);
                $res->end(json_encode($json));
                // } catch (\Throwable $e) {
                //   $status = $e->getCode() ?: 500;
                //   $res->header('Content-Type', 'application/json');
                //   $res->status($status);
                //   $res->end(json_encode(['error' => $e->getMessage()]));
                // }

                // Metrics + async logging
                $dur = microtime(true) - $start;
                $path = parse_url($req->server['request_uri'] ?? '/', PHP_URL_PATH);

                $counter->inc([$req->server['request_method'], $path, (string)$status]);
                $hist->observe($dur, [$req->server['request_method'], $path]);

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
            } finally {
                // Release MySQL connection back to pool
                if (isset($conn)) {
                    $this->mysql->put($conn);
                }
                if (isset($redisConn)) {
                    $this->redis->put($redisConn);
                }
            }
        });

        // Task handling for async jobs
        $this->http->on('task', function (SwooleHttp $server, \Swoole\Server\Task $task) {
            $data = $task->data;
            if (($data['type'] ?? '') === 'log') {
                LoggerTask::handle($data['data']);
            }
            return true;
        });

        $this->http->on('finish', fn() => null);
    }

    public function start(): void
    {
        $this->http->start();
    }
}
