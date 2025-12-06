<?php

/**
 * src/Core/Servers/HttpServer.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/HttpServer.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Container;
use App\Core\Router;
use Swoole\Http\Server;

/**
 * Class HttpServer
 * Handles all http server operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class HttpServer
{
    private readonly Server $server;
    private readonly Container $container;
    private readonly TableManager $tableManager;

    /**
     * @param array<string, mixed> $config Config
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function __construct(array $config, Router $router)
    {
        // Create Provider
        // Boot providers
        $serverFactory      = new ServerFactory();
        $this->tableManager = new TableManager();
        $this->server       = $serverFactory->create($config);
        $this->container    = new Container();

        $containerInitializer = new ContainerInitializer();
        $containerInitializer->bindCore($this->container, $this->server, $this->tableManager, $config);
        $containerInitializer->bindRouter($this->container, $router);

        $poolInitializer = new PoolInitializer();
        $poolInitializer->init($this->container, $config);

        $eventRegistrar = new EventRegistrar();
        $eventRegistrar->register($this->server, $this->container, $this->tableManager);
    }

    public function start(): void
    {
        $this->server->start();
    }
}
