<?php

/**
 * src/Middlewares/MetricsMiddleware.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
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

use App\Core\Channels\ChannelManager;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Metrics;
use App\Core\Router;
use App\Tasks\HttpMetricsTask;

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
    public const TAG = 'MetricsMiddleware';

    public function __construct(
        private readonly Router $router,
        private readonly ChannelManager $channelManager
    ) {
        // Empty Constructor
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        $start = microtime(true);

        $next($request, $response); // call next middleware

        $dur    = microtime(true) - $start;
        $method = $request->getMethod();
        $path   = $request->getPath();
        $status = $response->getStatus();

        // Ignore health/metrics endpoints
        if (in_array($path, ['/health', '/health.html', '/metrics'], true)) {
            return;
        }

        // Resolve route for consistent labels
        [$route] = $this->router->getRouteByPath($method, $path);
        if ($route === null) {
            return;
        }

        // Dispatch async user creation task
        $id     = bin2hex(random_bytes(8));
        $result = $this->channelManager->push([
            'class'     => HttpMetricsTask::class,
            'id'        => $id,
            'arguments' => [$method, $route['path'], $status, $dur],
        ]);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push called'));

        // check if unable to push
        if ($result === false) {
            // Log warning
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push failed'));
        }
    }
}
