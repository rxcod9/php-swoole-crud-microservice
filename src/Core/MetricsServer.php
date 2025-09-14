<?php

namespace App\Core;

use Prometheus\RenderTextFormat;
use Swoole\Http\Server;
use Swoole\Http\Response;
use Swoole\Http\Request;

if (!defined('SWOOLE_BASE')) {
    define('SWOOLE_BASE', 2);
}

final class MetricsServer
{
    private int $port;

    public function __construct(int $port = 9310)
    {
        $this->port = $port;
    }

    public function start(): void
    {
        $server = new Server('0.0.0.0', $this->port, SWOOLE_BASE);

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
