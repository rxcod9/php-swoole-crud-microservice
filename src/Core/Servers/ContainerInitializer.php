<?php

/**
 * src/Core/Servers/ContainerInitializer.php
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
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/ContainerInitializer.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Config;
use App\Core\Container;
use App\Core\Events\RouteDispatcher;
use App\Core\Router;
use App\Tables\TableWithLRUAndGC;
use Swoole\Http\Server;
use Swoole\Table;

/**
 * Class ContainerInitializer
 * Handles all container initializer operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class ContainerInitializer
{
    /**
     * @param array<string, mixed> $config Config
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function bindCore(Container $container, Server $server, TableManager $tableManager, array $config): void
    {
        $container->bind(Server::class, fn (): Server => $server);
        $container->bind(Table::class, fn (): Table => $tableManager->healthTable);
        $container->bind(TableWithLRUAndGC::class, fn (): TableWithLRUAndGC => $tableManager->lruTable);
        $container->bind(Config::class, fn (): Config => new Config($config));
        $container->bind(Container::class, fn (): Container => $container);
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function bindRouter(Container $container, Router $router): void
    {
        $container->singleton(RouteDispatcher::class, fn (): RouteDispatcher => new RouteDispatcher($router, $container));
        $container->singleton(Router::class, fn (): Router => $router);
    }
}
