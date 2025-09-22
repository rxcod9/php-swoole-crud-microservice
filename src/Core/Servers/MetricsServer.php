<?php

namespace App\Core\Servers;

use App\Core\Metrics;
use Prometheus\RenderTextFormat;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

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
 *
 * Starts a Swoole HTTP server to serve Prometheus metrics.
 */
final class MetricsServer
{
    /**
     * @var int The port on which the metrics server will listen.
     */
    private int $port;

    /**
     * MetricsServer constructor.
     *
     * @param int $port The port to listen on (default: 9310).
     */
    public function __construct(int $port = 9310)
    {
        $this->port = $port;
    }

    /**
     * Starts the Swoole HTTP server to serve metrics.
     *
     * @return void
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
         * @return void
         */
        $server->on('request', function (Request $_, Response $response) {
            try {
                $renderer = new RenderTextFormat();

                $metrics = $renderer->render(Metrics::reg()->getMetricFamilySamples());

                $response->header('Content-Type', RenderTextFormat::MIME_TYPE);
                $response->end($metrics);
            } catch (\Throwable $e) {
                $response->status(500);
                $response->end(json_encode(['error' => $e->getMessage()]));
            }
        });

        $server->start();
    }
}
