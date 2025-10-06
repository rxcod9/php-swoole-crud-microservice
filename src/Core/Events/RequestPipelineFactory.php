<?php

/**
 * src/Core/Events/RequestPipelineFactory.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestPipelineFactory.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;

/**
 * Factory for creating MiddlewarePipeline instances
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestPipelineFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Create a new MiddlewarePipeline instance.
     */
    public function create(): MiddlewarePipeline
    {
        return new MiddlewarePipeline($this->container);
    }
}
