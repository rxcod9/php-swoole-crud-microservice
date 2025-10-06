<?php

/**
 * src/Core/Events/RequestHandler.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Dispatcher;
use App\Core\Messages;
use App\Core\Metrics;
use App\Core\Router;
use App\Middlewares\CompressionMiddleware;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\HideServerHeaderMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use ReflectionClass;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Throwable;

/**
 * Handles incoming HTTP requests, including routing, middleware, and response generation.
 * Also manages CORS headers and preflight requests.
 * Binds request-scoped dependencies like connection pools.
 * Logs request details asynchronously.
 * Provides health check endpoints.
 * Ensures worker readiness before processing requests.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class RequestHandler
{
    /**
     * RequestHandler constructor.
     *
     * @param Router    $router    Router instance for HTTP request routing
     * @param Server    $server    Swoole HTTP server instance
     * @param Container $container Dependency injection container
     */
    public function __construct(
        private Router $router,
        private Server $server,
        private Container $container
    ) {
        //
    }

    /**
     * Handle the incoming HTTP request.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The HTTP response to be sent
     */
    public function __invoke(Request $request, Response $response): void
    {
        try {
            $workerReadyChecker = new WorkerReadyChecker();
            $workerReadyChecker->wait();

            $reqId = bin2hex(random_bytes(8));
            $start = microtime(true);

            // Prepare middleware pipeline
            $middlewarePipeline = new MiddlewarePipeline($this->container);
            $this->registerGlobalMiddlewares($middlewarePipeline);

            // Run pipeline + final dispatcher
            $middlewarePipeline->handle(
                $request,
                $response,
                fn () => $this->dispatchRouteMiddlewarePipeline($request, $reqId, $response, $start)
            );
        } catch (Throwable $throwable) {
            $this->handleException($request, $response, $throwable);
        }
    }

    /**
     * Register global middleware in the intended order.
     */
    private function registerGlobalMiddlewares(MiddlewarePipeline $middlewarePipeline): void
    {
        $middlewarePipeline->addMiddleware(CorsMiddleware::class);
        $middlewarePipeline->addMiddleware(SecurityHeadersMiddleware::class);
        $middlewarePipeline->addMiddleware(RateLimitMiddleware::class);
        $middlewarePipeline->addMiddleware(HideServerHeaderMiddleware::class);
        $middlewarePipeline->addMiddleware(CompressionMiddleware::class);
    }

    /**
     * Centralized exception handler for all request failures.
     */
    private function handleException(Request $request, Response $response, Throwable $throwable): void
    {
        $status = is_int($throwable->getCode()) ? $throwable->getCode() : 500;

        $response->header('Content-Type', 'application/json');
        $response->status($status);
        $response->end(json_encode([
            'error'      => $this->getErrorMessage($throwable),
            'error_full' => $throwable->getMessage(),
            'code'       => $status,
            'trace'      => $throwable->getTraceAsString(),
            'file'       => $throwable->getFile(),
            'line'       => $throwable->getLine(),
        ]));

        $requestLogger = new RequestLogger();
        $requestLogger->log(
            'error',
            $this->server,
            $request,
            [
                'error' => $throwable->getMessage(),
                'code'  => $status,
                'trace' => $throwable->getTraceAsString(),
                'file'  => $throwable->getFile(),
                'line'  => $throwable->getLine(),
            ]
        );
    }

    /**
     * Centralized exception handler for all request failures.
     */
    private function getErrorMessage(Throwable $throwable): string
    {
        if ($this->isAppException($throwable)) {
            return $throwable->getMessage();
        }

        return Messages::ERROR_INTERNAL_ERROR;
    }

    /**
     * Determine if the given Throwable belongs to the App\Exception namespace.
     *
     */
    public static function isAppException(Throwable $throwable): bool
    {
        $reflectionClass = new ReflectionClass($throwable);
        $namespace       = $reflectionClass->getNamespaceName();

        // You can tweak this prefix to match your actual project namespace
        return str_starts_with($namespace, 'App\\Exceptions');
    }

    /**
     * Dispatch the request to the appropriate controller action and send the response.
     *
     * @param Request  $request  The incoming HTTP request
     * @param string   $reqId    Unique request ID for logging
     * @param Response $response The HTTP response to be sent
     * @param float    $start    Timestamp when the request handling started (for metrics)
     */
    private function dispatchRouteMiddlewarePipeline(
        Request $request,
        string $reqId,
        Response $response,
        float $start
    ): void {
        // Route resolution
        [$action, $params, $routeMiddlewares] = $this->router->match(
            $request->server['request_method'],
            $request->server['request_uri']
        );

        // Prepare middleware pipeline
        $middlewarePipeline = new MiddlewarePipeline($this->container);
        $middlewarePipeline->addMiddlewares($routeMiddlewares);

        // Run pipeline + final dispatcher
        $middlewarePipeline->handle(
            $request,
            $response,
            fn () => $this->dispatch($action, $params, $request, $reqId, $response, $start)
        );
    }

    /**
     * Dispatch the request to the appropriate controller action and send the response.
     *
     * @param mixed                $action   The action handler (e.g. controller@method)
     * @param array<string,string> $params   Route parameters extracted from the URL
     * @param Request              $request  The incoming HTTP request
     * @param string               $reqId    Unique request ID for logging
     * @param Response             $response The HTTP response to be sent
     * @param float                $start    Timestamp when the request handling started (for metrics)
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function dispatch(
        string $action,
        array $params,
        Request $request,
        string $reqId,
        Response $response,
        float $start
    ): void {
        // Metrics setup
        $reg     = Metrics::reg();
        $counter = $reg->getOrRegisterCounter(
            'http_requests_total',
            'Requests',
            'Total HTTP requests',
            ['method', 'path', 'status']
        );
        $histogram = $reg->getOrRegisterHistogram(
            'http_request_seconds',
            'Latency',
            'HTTP request latency',
            ['method', 'path']
        );

        // Execute controller/handler
        $dispatcher = new Dispatcher($this->container);
        $payload    = $dispatcher->dispatch($action, $params, $request);

        $path         = parse_url($request->server['request_uri'] ?? '/', PHP_URL_PATH);
        $status       = $payload['__status'] ?? 200;
        $json         = $payload['__json'] ?? null;
        $html         = $payload['__html'] ?? null;
        $text         = $payload['__text'] ?? null;
        $ctype        = $payload['__contentType'] ?? null;
        $cacheTagType = $payload['__cacheTagType'] ?? null;

        $response->status($status);
        $response->header('X-Cache-Type', $cacheTagType);
        // Format response
        if ($html) {
            $response->header('Content-Type', $ctype ?? 'text/html');
            $response->end($status === 204 ? '' : $html);
        } elseif ($text) {
            $response->header('Content-Type', $ctype ?? 'text/plain');
            $response->end($status === 204 ? '' : $text);
        } else {
            $response->header('Content-Type', $ctype ?? 'application/json');
            $response->end($status === 204 ? '' : json_encode($json ?: $payload));
        }

        // Metrics & Logging
        $dur = microtime(true) - $start;

        if (!in_array($path, ['/health', '/health.html', '/metrics'], true)) {
            [$route] = $this->router->getRouteByPath(
                $request->server['request_method'],
                $path ?? '/'
            );
            $counter->inc([$request->server['request_method'], $route['path'], (string) $status]);
            $histogram->observe($dur, [$request->server['request_method'], $route['path']]);
        }

        $requestLogger = new RequestLogger();
        $requestLogger->log(
            'debug',
            $this->server,
            $request,
            [
                'id'     => $reqId,
                'method' => $request->server['request_method'],
                'path'   => $path,
                'status' => $status,
                'dur_ms' => (int) round($dur * 1000),
            ]
        );
    }
}
