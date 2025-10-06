<?php

/**
 * src/Core/Events/RequestServices.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: Consolidated request-related services for PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestFactories.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestDispatcher;

/**
 * Factory container for creating pipelines and dispatchers.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class RequestFactories
{
    public function __construct(
        public RequestPipelineFactory $pipelineFactory,
        public RequestDispatcherFactory $dispatcherFactory
    ) {
    }

    /**
     * Create a middleware pipeline.
     */
    public function createPipeline(): MiddlewarePipeline
    {
        return $this->pipelineFactory->create();
    }

    /**
     * Create a request dispatcher.
     */
    public function createDispatcher(): RequestDispatcher
    {
        return $this->dispatcherFactory->create();
    }
}
