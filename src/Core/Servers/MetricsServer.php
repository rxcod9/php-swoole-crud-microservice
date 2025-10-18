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
use App\Core\Pools\RedisPool;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Redis;
use ReflectionClass;
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
    public const TAG = 'MetricsServer';

    /**
     * MetricsServer constructor.
     *
     * @param array<string, mixed> $config Configuration array containing Redis settings.
     * @param int $port The port to listen on (default: 9310).
     */
    public function __construct(
        private array $config,
        private int $port = 9310
    ) {
        // Empty Constructor
    }

    /**
     * Starts the Swoole HTTP server to serve metrics.
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function start(): void
    {
        $server = new Server('0.0.0.0', $this->port, SWOOLE_BASE);

        /**
         * Handles incoming HTTP requests and serves Prometheus metrics.
         *
         * @param Request  $_        The incoming HTTP request (unused).
         * @param Response $response The HTTP response object.
         */
        $server->on('request', function (Request $request, Response $response): void {
            try {
                $redisConf = $this->config['redis'];
                $redisPool = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);

                $redisPool->init(-1);
                $redis             = $redisPool->get();
                $collectorRegistry = new CollectorRegistry(
                    Redis::fromExistingConnection($redis)
                );

                $renderTextFormat = new RenderTextFormat();

                $metrics = $renderTextFormat->render($collectorRegistry->getMetricFamilySamples());

                $response->header('Content-Type', RenderTextFormat::MIME_TYPE);
                $response->end($metrics);
            } catch (Throwable $throwable) {
                logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
                $response->status(500);
                $response->end(json_encode(['error' => $this->getErrorMessage($throwable)]));
            } finally {
                if (isset($redisPool, $redis)) {
                    $redisPool->put($redis);
                }
            }
        });

        $server->start();
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
     */
    private function isAppException(Throwable $throwable): bool
    {
        $reflectionClass = new ReflectionClass($throwable);
        $namespace       = $reflectionClass->getNamespaceName();

        // You can tweak this prefix to match your actual project namespace
        return str_starts_with($namespace, 'App\\Exceptions');
    }
}
