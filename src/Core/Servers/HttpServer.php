<?php

declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Container;
use App\Core\Contexts\AppContext;
use App\Core\Events\PoolBinder;
use App\Core\Events\RequestHandler;
use App\Core\Events\TaskFinishHandler;
use App\Core\Events\TaskRequestHandler;
use App\Core\Events\WorkerStartHandler;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;
use App\Core\Router;
use App\Services\Cache\CacheService;
use App\Tables\TableWithLRUAndGC;
use Swoole\Http\Server;
use Swoole\Table;

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
 *   - Health check endpoint (/health) reporting worker and pool status.
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
     * @var Server Swoole HTTP server instance
     */
    private Server $server;

    /**
     * HttpServer constructor.
     *
     * Initializes the Swoole HTTP server, sets up event handlers, shared memory table,
     * connection pools, and request handling logic.
     *
     * @param array $config Server configuration array
     * @param Router $router Router instance for HTTP request routing
     */
    public function __construct(
        private array $config,
        private Router $router
    ) {
        // Shared memory table for worker health
        $table = new Table(64, 128);
        $table->column('pid', Table::TYPE_INT, 4);
        $table->column('timer_id', Table::TYPE_INT, 4);
        $table->column('first_heartbeat', Table::TYPE_INT, 10);
        $table->column('last_heartbeat', Table::TYPE_INT, 10);
        $table->column('mysql_capacity', Table::TYPE_INT, 8);
        $table->column('mysql_available', Table::TYPE_INT, 8);
        $table->column('mysql_created', Table::TYPE_INT, 8);
        $table->column('mysql_in_use', Table::TYPE_INT, 8);
        $table->column('redis_capacity', Table::TYPE_INT, 8);
        $table->column('redis_available', Table::TYPE_INT, 8);
        $table->column('redis_created', Table::TYPE_INT, 8);
        $table->column('redis_in_use', Table::TYPE_INT, 8);
        $table->create();

        // Shared memory table for worker health
        $cacheTable = new TableWithLRUAndGC(500, 600);
        $cacheTable->create();

        // Shared memory table for worker health
        $rateLimitTable = new Table(64, 128);
        $rateLimitTable->column('count', Table::TYPE_INT, 8);
        $rateLimitTable->create();

        $host = $this->config['server']['host'];
        $port = $this->config['server']['http_port'];

        $this->server = new Server(
            $host,
            $port,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP | ((($this->config['server']['ssl']['enable'] ?? false) === true) ? SWOOLE_SSL : 0)
        );

        // Optional TLS/HTTP2 support
        if (($this->config['server']['ssl']['enable'] ?? false) === true) {
            $this->server->set([
                'ssl_cert_file'       => $this->config['server']['ssl']['cert_file'],
                'ssl_key_file'        => $this->config['server']['ssl']['key_file'],
                'open_http2_protocol' => true, // optional
            ]);
        }

        $this->server->set($this->config['server']['settings'] ?? []);

        // Server start event
        $this->server->on(
            'Start',
            fn () => print "HTTP listening on {$host}:{$port}\n"
        );

        $container = new Container();
        $container->bind(Server::class, fn () => $this->server);
        $container->bind(Table::class, fn () => $table);
        $container->bind(TableWithLRUAndGC::class, fn () => $cacheTable);

        // Initialize pools per worker
        $dbConf = $this->config['db'][$this->config['db']['driver'] ?? 'mysql'];
        $mysql  = new PDOPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);

        \Swoole\Coroutine\run(function () use ($mysql) {
            $mysql->init(); // now inside coroutine
        });

        $redisConf = $this->config['redis'];
        $redis     = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);

        \Swoole\Coroutine\run(function () use ($redis) {
            $redis->init(); // now inside coroutine
        });

        new PoolBinder($mysql, $redis)->bind($container);

        $cacheService = $container->get(CacheService::class);
        $container->bind(CacheService::class, fn () => $cacheService);

        $workerStartHandler = new WorkerStartHandler($config, $table, $cacheService, $mysql, $redis);

        // Worker start event
        $this->server->on(
            'WorkerStart',
            $workerStartHandler
        );

        // Worker stop event (graceful)
        $this->server->on(
            'WorkerStop',
            function (
                Server $server,
                int $workerId
            ) use ($table, $workerStartHandler) {
                echo "[WorkerStop] Worker #{$workerId} stopped\n";
                $workerStartHandler->clearTimers($workerId);
                $this->disableWorker($workerId, $table);
            }
        );

        // Worker exit event (after WorkerStop)
        $this->server->on(
            'WorkerExit',
            function (
                Server $server,
                int $workerId
            ) use ($table, $workerStartHandler) {
                echo "[WorkerExit] Worker #{$workerId} exited\n";
                $workerStartHandler->clearTimers($workerId);
                $this->disableWorker($workerId, $table);
            }
        );

        // Worker error event (crash/fatal error)
        $this->server->on(
            'WorkerError',
            function (
                Server $server,
                int $workerId,
                int $workerPid,
                int $exitCode,
                int $signal
            ) use ($table, $workerStartHandler) {
                echo "[WorkerError] Worker #{$workerId} (PID: {$workerPid}) crashed. Exit code: {$exitCode}, Signal: {$signal}\n";
                $workerStartHandler->clearTimers($workerId);
                $this->disableWorker($workerId, $table);
            }
        );

        // HTTP request event
        $this->server->on(
            'request',
            new RequestHandler(
                $router,
                $this->server,
                $table,
                $rateLimitTable,
                $container
            )
        );

        // Task event handler for async jobs (e.g., logging)
        $this->server->on(
            'task',
            new TaskRequestHandler(
                $table,
                $container
            )
            // new TaskHandler()
        );

        // Task finish event (no-op)
        $this->server->on('finish', new TaskFinishHandler());
    }

    /**
     * Start the HTTP server.
     *
     * Boots the Swoole HTTP server and begins accepting requests.
     *
     */
    public function start(): void
    {
        $this->server->start();
    }

    /**
     * Disable a worker and remove its entry from the health table.
     *
     * @param string $workerId The ID of the worker to disable
     */
    private function disableWorker(
        int $workerId,
        Table $table
    ): void {
        AppContext::setWorkerReady(false);
        $table->del((string)$workerId);
    }
}
