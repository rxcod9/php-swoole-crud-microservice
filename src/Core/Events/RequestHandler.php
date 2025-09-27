<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Dispatcher;
use App\Core\Metrics;
use App\Core\Router;

use function in_array;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Table;
use Throwable;

/**
 * Handles incoming HTTP requests, including routing, middleware, and response generation.
 * Also manages CORS headers and preflight requests.
 * Binds request-scoped dependencies like DbContext and connection pools.
 * Logs request details asynchronously.
 * Provides health check endpoints.
 * Ensures worker readiness before processing requests.
 *
 * @package App\Core\Events
 * @version 1.0.0
 * @since 1.0.0
 */
final class RequestHandler
{
    /**
     * RequestHandler constructor.
     *
     * @param Router $router Router instance for HTTP request routing
     * @param Server $server Swoole HTTP server instance
     * @param Table $table Shared memory table for worker health checks
     * @param Table $rateLimitTable Shared table for rate limiting
     * @param Container $container Dependency injection container
     */
    public function __construct(
        private Router $router,
        private Server $server,
        private Table $table,
        private Table $rateLimitTable,
        private Container $container
    ) {
        //
    }

    /**
     * Handle the incoming HTTP request.
     *
     * @param Request $req The incoming HTTP request
     * @param Response $res The HTTP response to be sent
     */
    public function __invoke(Request $req, Response $res): void
    {
        try {
            new WorkerReadyChecker()->wait();

            $reqId = bin2hex(random_bytes(8));
            $start = microtime(true);

            new PoolBinder()->bind($this->server, $this->container);

            // Prepare middleware pipeline
            $pipeline = new MiddlewarePipeline();
            $this->registerGlobalMiddlewares($pipeline);

            // Route resolution
            [$action, $params, $routeMiddlewares] = $this->router->match(
                $req->server['request_method'],
                $req->server['request_uri']
            );

            // Run pipeline + final dispatcher
            $pipeline->handle(
                $req,
                $res,
                $this->container,
                $routeMiddlewares,
                fn () => $this->dispatch($action, $params, $req, $reqId, $res, $start)
            );
        } catch (Throwable $e) {
            $this->handleException($req, $res, $e);
        }
    }

    /**
     * Register global middleware in the intended order.
     */
    private function registerGlobalMiddlewares(MiddlewarePipeline $pipeline): void
    {
        $pipeline->addMiddleware(new \App\Middlewares\CorsMiddleware());
        // $pipeline->addMiddleware(new \App\Middlewares\AuthMiddleware());
        // $pipeline->addMiddleware(new \App\Middlewares\RateLimitMiddleware($this->rateLimitTable));
        $pipeline->addMiddleware(new \App\Middlewares\SecurityHeadersMiddleware());
        // $pipeline->addMiddleware(new \App\Middlewares\LoggingMiddleware());
        // $pipeline->addMiddleware(new \App\Middlewares\MetricsMiddleware());
        $pipeline->addMiddleware(new \App\Middlewares\HideServerHeaderMiddleware());
        // $pipeline->addMiddleware(new \App\Middlewares\CompressionMiddleware());
    }

    /**
     * Centralized exception handler for all request failures.
     */
    private function handleException(Request $req, Response $res, Throwable $e): void
    {
        $status = $e->getCode() ?: 500;

        $res->header('Content-Type', 'application/json');
        $res->status($status);
        $res->end(json_encode([
            'error' => $e->getMessage(),
            'code'  => $status,
            'trace' => $e->getTraceAsString(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]));

        new RequestLogger()->log($this->server, $req, [
            'error' => $e->getMessage(),
            'code'  => $status,
            'trace' => $e->getTraceAsString(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]);
    }

    /**
     * Dispatch the request to the appropriate controller action and send the response.
     *
     * @param mixed $action The action handler (e.g. controller@method)
     * @param array<string,string> $params Route parameters extracted from the URL
     * @param Request $req The incoming HTTP request
     * @param string $reqId Unique request ID for logging
     * @param Response $res The HTTP response to be sent
     * @param float $start Timestamp when the request handling started (for metrics)
     *
     */
    private function dispatch(
        string $action,
        array $params,
        Request $req,
        string $reqId,
        Response $res,
        float $start
    ): void {
        // Metrics setup
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

        // Execute controller/handler
        $payload = new Dispatcher($this->container)->dispatch($action, $params, $req);

        $path = parse_url($req->server['request_uri'] ?? '/', PHP_URL_PATH);
        $status = $payload['__status'] ?? 200;
        $json = $payload['__json'] ?? null;
        $html = $payload['__html'] ?? null;
        $text = $payload['__text'] ?? null;
        $ctype = $payload['__contentType'] ?? null;

        // Format response
        if ($html) {
            $res->header('Content-Type', $ctype ?? 'text/html');
            $res->end($status === 204 ? '' : $html);
        } elseif ($text) {
            $res->header('Content-Type', $ctype ?? 'text/plain');
            $res->end($status === 204 ? '' : $text);
        } else {
            $res->header('Content-Type', $ctype ?? 'application/json');
            $res->end($status === 204 ? '' : json_encode($json ?: $payload));
        }
        $res->status($status);

        // Metrics & Logging
        $dur = microtime(true) - $start;

        if (!in_array($path, ['/health', '/health.html', '/metrics'], true)) {
            [$route, $actionMeta] = $this->router->getRouteByPath(
                $req->server['request_method'],
                $path ?? '/'
            );
            $counter->inc([$req->server['request_method'], $route['path'], (string) $status]);
            $hist->observe($dur, [$req->server['request_method'], $route['path']]);
        }

        new RequestLogger()->log($this->server, $req, [
            'id'     => $reqId,
            'method' => $req->server['request_method'],
            'path'   => $path,
            'status' => $status,
            'dur_ms' => (int) round($dur * 1000),
        ]);
    }
}
