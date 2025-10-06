<?php

/**
 * src/Core/Events/RequestDispatcherFactory.php
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
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestDispatcherFactory.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Events\Request\RequestDispatcher;
use App\Core\Router;
use Swoole\Http\Server;

/**
 * Factory for creating RequestDispatcher instances
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestDispatcherFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly Server $server
    ) {
    }

    /**
     * Create a new RequestDispatcher instance.
     */
    public function create(): RequestDispatcher
    {
        return new RequestDispatcher($this->container, $this->router, $this->server);
    }
}
