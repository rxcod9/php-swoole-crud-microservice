<?php

/**
 * src/Core/Events/Request/RequestMetricsLogger.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events\Request
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/Request/RequestMetricsLogger.php
 */
declare(strict_types=1);

namespace App\Core\Events\Request;

use Swoole\Http\Server;

/**
 * Logs metrics for completed requests.
 *
 * @category  Core
 * @package   App\Core\Events\Request
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestMetricsLogger
{
    /**
     * Log request metrics.
     */
    public function log(Server $server, RequestContext $requestContext): void
    {
        $payload = [
            'id'     => $requestContext->reqId,
            'method' => $requestContext->request->server['request_method'] ?? '',
            'path'   => parse_url($requestContext->request->server['request_uri'] ?? '/', PHP_URL_PATH),
        ];

        $duration = microtime(true) - $requestContext->start;

        $metrics           = new Metrics($payload, $duration);
        $logIdentity       = new LogIdentity('info', $server, $requestContext);
        $requestLogContext = new RequestLogContext($logIdentity, $metrics);

        // For now, log via simple error_log; can integrate Monolog or async logging
        error_log(json_encode([
            'level'       => $requestLogContext->identity->level,
            'request'     => $requestLogContext->identity->requestContext->reqId,
            'duration_ms' => (int) round($requestLogContext->metrics->duration * 1000),
            'payload'     => $requestLogContext->metrics->payload,
        ]));
    }
}
