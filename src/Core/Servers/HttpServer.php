<?php

/**
 * src/Core/Servers/HttpServer.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/HttpServer.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Config;
use App\Core\Container;
use App\Core\Contexts\AppContext;
use App\Core\Events\PoolBinder;
use App\Core\Events\RequestHandler;
use App\Core\Events\TaskFinishHandler;
use App\Core\Events\TaskRequestHandler;
use App\Core\Events\WorkerStartHandler;
use App\Core\Pools\PDOPool;
use App\Core\Pools\PoolFacade;
use App\Core\Pools\RedisPool;
use App\Core\Router;
use App\Services\Cache\CacheService;
use App\Tables\TableWithLRUAndGC;
use Swoole\Http\Server;
use Swoole\Table;

/**
 * Class HttpServer
 * Sets up and manages the Swoole HTTP server, including:
 * - Server initialization with configuration, TLS/HTTP2 support, and worker management.
 * - Shared memory table for worker health monitoring and liveness checks.
 * - Event handlers for server lifecycle: Start, WorkerStart, WorkerStop, WorkerExit, WorkerError.
 * - Per-worker initialization of MySQL and Redis connection pools.
 * - HTTP request handling with:
 * - CORS headers and preflight OPTIONS support.
 * - Worker readiness checks before processing requests.
 * - Dependency injection container for request-scoped services.
 * - Authentication middleware.
 * - Health check endpoint (/health) reporting worker and pool status.
 * - Routing and dispatching to controllers/actions.
 * - Metrics collection (request count and latency).
 * - Asynchronous logging via Swoole task workers.
 * - Graceful resource cleanup after each request.
 * - Task event handler for background jobs (e.g., logging).
 * Usage:
 * $server = new HttpServer($config, $router);
 * $server->start();
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class HttpServer
{
    /** @var Server Swoole HTTP server instance */
    private Server $server;

    private Table $healthTable;

    /** @var TableWithLRUAndGC<string, array<string, mixed>> LRU cache table with garbage collection */
    private TableWithLRUAndGC $lruTable;

    /** @var TableWithLRUAndGC<string, array<string, mixed>> Rate limiter table */
    private TableWithLRUAndGC $rateLimitTable;

    private Container $container;

    /**
     * HttpServer constructor.
     *
     * Initializes the Swoole HTTP server, sets up event handlers, shared memory table,
     * connection pools, and request handling logic.
     *
     * @param array<string, mixed> $config Server configuration array
     * @param Router            $router Router instance for HTTP request routing
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    /**
     * HttpServer constructor.
     *
     * @param array<string, mixed> $config  Server configuration array
     * @param Router               $router  Router instance for request routing
     */
    public function __construct(
        private array $config,
        private readonly Router $router
    ) {
        $this->initTables();
        $this->initServer();
        $this->initContainer();
        $this->initPools();
        $this->initEventHandlers();
    }

    /**
     * Initializes shared memory tables used across workers.
     */
    private function initTables(): void
    {
        // Worker health table
        $this->healthTable = new Table(64);
        $this->healthTable->column('pid', Table::TYPE_INT, 8);
        $this->healthTable->column('timer_id', Table::TYPE_INT, 8);
        $this->healthTable->column('first_heartbeat', Table::TYPE_INT, 10);
        $this->healthTable->column('last_heartbeat', Table::TYPE_INT, 10);
        $this->healthTable->column('mysql_capacity', Table::TYPE_INT, 3);
        $this->healthTable->column('mysql_available', Table::TYPE_INT, 3);
        $this->healthTable->column('mysql_created', Table::TYPE_INT, 3);
        $this->healthTable->column('mysql_in_use', Table::TYPE_INT, 3);
        $this->healthTable->column('redis_capacity', Table::TYPE_INT, 3);
        $this->healthTable->column('redis_available', Table::TYPE_INT, 3);
        $this->healthTable->column('redis_created', Table::TYPE_INT, 3);
        $this->healthTable->column('redis_in_use', Table::TYPE_INT, 3);
        $this->healthTable->create();

        // LRU cache table with garbage collection
        $this->lruTable = new TableWithLRUAndGC(8192, 600);
        $this->lruTable->create();

        // Rate limiter shared table
        $this->rateLimitTable = new TableWithLRUAndGC(60);
        $this->rateLimitTable->create();
    }

    /**
     * Initializes and configures the Swoole HTTP server.
     */
    private function initServer(): void
    {
        $host       = $this->config['server']['host'];
        $port       = $this->config['server']['http_port'];
        $sslEnabled = (bool) $this->config['server']['ssl']['enable'];

        $this->server = new Server(
            $host,
            $port,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP | ($sslEnabled ? SWOOLE_SSL : 0)
        );

        if ($sslEnabled) {
            $this->server->set([
                'ssl_cert_file'       => $this->config['server']['ssl']['cert_file'],
                'ssl_key_file'        => $this->config['server']['ssl']['key_file'],
                'open_http2_protocol' => true,
            ]);
        }

        $this->server->set($this->config['server']['settings'] ?? []);

        // Server start event
        $this->server->on('Start', function () use ($host, $port): void {
            echo sprintf('HTTP listening on %s:%s%s', $host, $port, PHP_EOL);
        });
    }

    /**
     * Initializes dependency injection container and base bindings.
     */
    private function initContainer(): void
    {
        $this->container = new Container();

        // Initialize pools per worker
        $this->container->bind(Server::class, fn (): Server => $this->server);
        $this->container->bind(Table::class, fn (): Table => $this->healthTable);
        $this->container->bind(TableWithLRUAndGC::class, fn (): TableWithLRUAndGC => $this->lruTable);
        $this->container->bind(Config::class, fn (): Config => new Config($this->config));
        $this->container->bind(Container::class, fn (): Container => $this->container);
    }

    /**
     * Initializes connection pools for MySQL and Redis, then binds them to container.
     */
    private function initPools(): void
    {
        $dbConf  = $this->config['db'][$this->config['db']['driver'] ?? 'mysql'];
        $pdoPool = new PDOPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);

        \Swoole\Coroutine\run(fn () => $pdoPool->init(-1));

        $redisConf = $this->config['redis'];
        $redisPool = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);

        \Swoole\Coroutine\run(fn () => $redisPool->init(-1));

        $poolBinder = new PoolBinder($pdoPool, $redisPool);
        $poolBinder->bind($this->container);

        $cacheService = $this->container->get(CacheService::class);
        $this->container->bind(CacheService::class, fn (): CacheService => $cacheService);
    }

    /**
     * Registers all Swoole server lifecycle event handlers.
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    private function initEventHandlers(): void
    {
        $poolFacade = new PoolFacade(
            $this->container->get(PDOPool::class),
            $this->container->get(RedisPool::class),
            $this->container->get(CacheService::class)
        );

        // Worker stop event (graceful)
        $workerStartHandler = new WorkerStartHandler($this->healthTable, $poolFacade);

        // Worker lifecycle events
        $this->server->on('WorkerStart', $workerStartHandler);
        $this->server->on(
            'WorkerStop',
            fn (Server $server, int $workerId) => $this->handleWorkerStop($workerId, $workerStartHandler)
        );
        // Worker exit event (after WorkerStop)
        $this->server->on(
            'WorkerExit',
            fn (Server $server, int $workerId) => $this->handleWorkerStop($workerId, $workerStartHandler)
        );
        // Worker error event (crash/fatal error)
        $this->server->on(
            'WorkerError',
            fn (Server $server, int $workerId, int $workerPid, int $exitCode, int $signal) => $this->handleWorkerError($workerId, $workerPid, $exitCode, $signal, $workerStartHandler)
        );

        // HTTP request event
        // HTTP + Task handling
        $this->server->on('request', new RequestHandler($this->router, $this->server, $this->container));
        $this->server->on('task', new TaskRequestHandler($this->container));
        $this->server->on('finish', new TaskFinishHandler());
    }

    // Task event handler for async jobs (e.g., logging)
    /**
     * Handles worker stop/exit logic.
     */
    private function handleWorkerStop(int $workerId, WorkerStartHandler $workerStartHandler): void
    {
        echo "[WorkerStop] Worker #{$workerId} stopped\n";
        $workerStartHandler->clearTimers($workerId);
        $this->disableWorker($workerId, $this->healthTable);
    }

    /**
     * Handles worker crash/error logic.
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private function handleWorkerError(
        int $workerId,
        int $workerPid,
        int $exitCode,
        int $signal,
        WorkerStartHandler $workerStartHandler
    ): void {
        echo sprintf('[WorkerError] Worker #%d (PID: %d) crashed. Exit code: %d, Signal: %d%s', $workerId, $workerPid, $exitCode, $signal, PHP_EOL);
        $workerStartHandler->clearTimers($workerId);
        $this->disableWorker($workerId, $this->healthTable);
    }

    /**
     * Start the HTTP server.
     *
     * Boots the Swoole HTTP server and begins accepting requests.
     */
    public function start(): void
    {
        $this->server->start();
    }

    /**
     * Disable a worker and remove its entry from the health table.
     *
     * @param int $workerId The ID of the worker to disable
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function disableWorker(
        int $workerId,
        Table $table
    ): void {
        AppContext::setWorkerReady(false);
        if ($table->exist((string) $workerId)) {
            $table->del((string) $workerId);
        }
    }
}
