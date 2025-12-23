<?php

/**
 * src/Core/Events/RequestTelemetry.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
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

use App\Core\Channels\ChannelManager;
use App\Core\Events\Request\RequestContext;
use App\Core\Router;
use App\Tasks\HttpMetricsTask;
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
    public const TAG = 'RequestTelemetry';

    public function __construct(
        private readonly Router $router,
        private readonly ChannelManager $channelManager
    ) {
        // Empty constructor
    }

    public function collect(RequestContext $requestContext): void
    {
        try {
            $method  = $requestContext->exchange()->request()->getMethod();
            $path    = $requestContext->exchange()->request()->getPath();
            $dur     = $requestContext->duration();
            $status  = $requestContext->exchange()->response()->getStatus();
            [$route] = $this->router->getRouteByPath(
                $requestContext->exchange()->request()->getMethod(),
                $path
            );
            if ($route === null || in_array($path, ['/health', '/health.html', '/metrics'], true)) {
                return;
            }

            $start = microtime(true);
            // Dispatch async user creation task
            $id     = $requestContext->meta()->id();
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
        } catch (Throwable $throwable) {
            logDebug('RequestTelemetry', 'Metrics logging failed: ' . $throwable->getMessage());
        }
    }
}
