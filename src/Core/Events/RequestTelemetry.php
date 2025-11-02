<?php

/**
 * src/Core/Events/RequestTelemetry.php
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
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestTelemetry.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestContext;
use App\Core\Metrics;
use App\Core\Pools\RedisPool;
use App\Core\Router;
use Throwable;

/**
 * Class RequestTelemetry
 * Handles all request telemetry operations.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final class RequestTelemetry
{
    public function __construct(
        private readonly Router $router,
        private readonly RedisPool $redisPool,
        private readonly Metrics $metrics
    ) {
        // Empty constructor
    }

    public function collect(RequestContext $requestContext): void
    {
        // try {
        //     $path    = $requestContext->exchange()->request()->getPath();
        //     [$route] = $this->router->getRouteByPath(
        //         $requestContext->exchange()->request()->getMethod(),
        //         $path
        //     );
        //     if ($route === null || in_array($path, ['/health', '/metrics'], true)) {
        //         return;
        //     }

        //     $redis = $this->redisPool->get();
        //     defer(fn () => $this->redisPool->put($redis));

        //     $reg = $this->metrics->getCollectorRegistry($redis);

        //     $counter = $reg->getOrRegisterCounter(
        //         'http_requests_total',
        //         'Requests',
        //         'Total HTTP requests',
        //         ['method', 'path', 'status']
        //     );

        //     $histogram = $reg->getOrRegisterHistogram(
        //         'http_request_seconds',
        //         'Latency',
        //         'HTTP request latency',
        //         ['method', 'path']
        //     );

        //     $counter->inc([$requestContext->exchange()->request()->getMethod(), $route['path'], (string)$requestContext->exchange()->response()->getStatus()]);
        //     $histogram->observe($requestContext->duration(), [$requestContext->exchange()->request()->getMethod(), $route['path']]);
        // } catch (Throwable $throwable) {
        //     logDebug('RequestTelemetry', 'Metrics logging failed: ' . $throwable->getMessage());
        // }
    }
}
