<?php

/**
 * src/Middlewares/MetricsMiddleware.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/MetricsMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Metrics;
use App\Core\Pools\RedisPool;
use App\Core\Router;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class MetricsMiddleware
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class MetricsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RedisPool $redisPool,
        private readonly Metrics $metrics,
        private readonly Router $router
    ) {
        //
    }

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        $start = microtime(true);

        $next($request, $response); // call next middleware

        $dur   = microtime(true) - $start;
        $redis = $this->redisPool->get();
        defer(fn () => $this->redisPool->put($redis));

        $collectorRegistry = $this->metrics
            ->getCollectorRegistry($redis);
        $counter   = $collectorRegistry->getOrRegisterCounter('http_requests_total', 'Requests', 'Total HTTP requests', ['method', 'path', 'status']);
        $histogram = $collectorRegistry->getOrRegisterHistogram('http_request_seconds', 'Latency', 'HTTP request latency', ['method', 'path']);

        $path   = parse_url($request->server['request_uri'] ?? '/', PHP_URL_PATH);
        $status = $response->status ?? 200;

        [$route] = $this->router->getRouteByPath(
            $request->server['request_method'],
            $path ?? '/'
        );

        if ($route && !in_array($path, ['/health', '/health.html', '/metrics'], true)) {
            $counter->inc([$request->server['request_method'], $route['path'], (string) $status]);
            $histogram->observe($dur, [$request->server['request_method'], $route['path']]);
        }
    }
}
