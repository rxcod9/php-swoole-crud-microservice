<?php

/**
 * src/Core/Servers/MetricsServer.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/MetricsServer.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Messages;
use App\Core\Metrics;
use Prometheus\RenderTextFormat;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Throwable;

/**
 * MetricsServer
 *
 * A simple HTTP server using Swoole to expose Prometheus metrics.
 *
 * @package App\Core
 */
if (!defined('SWOOLE_BASE')) {
    define('SWOOLE_BASE', 2);
}

/**
 * Class MetricsServer
 * Starts a Swoole HTTP server to serve Prometheus metrics.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class MetricsServer
{
    /**
     * MetricsServer constructor.
     *
     * @param int $port The port to listen on (default: 9310).
     */
    public function __construct(private int $port = 9310)
    {
    }

    /**
     * Starts the Swoole HTTP server to serve metrics.
     * 
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function start(): void
    {
        $server = new Server('0.0.0.0', $this->port, SWOOLE_BASE);

        /**
         * Handles incoming HTTP requests and serves Prometheus metrics.
         *
         * @param Request  $_        The incoming HTTP request (unused).
         * @param Response $response The HTTP response object.
         *
         * @SuppressWarnings(PHPMD.StaticAccess)
         * @SuppressWarnings(PHPMD.UnusedFormalParameter)
         */
        $server->on('request', function (Request $request, Response $response): void {
            try {
                $renderTextFormat = new RenderTextFormat();

                $metrics = $renderTextFormat->render(Metrics::reg()->getMetricFamilySamples());

                $response->header('Content-Type', RenderTextFormat::MIME_TYPE);
                $response->end($metrics);
            } catch (Throwable $throwable) {
                error_log('Exception: ' . $throwable->getMessage()); // logged internally
                $response->status(500);
                $response->end(json_encode(['error' => Messages::ERROR_INTERNAL_ERROR]));
            }
        });

        $server->start();
    }
}
