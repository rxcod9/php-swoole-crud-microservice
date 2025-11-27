<?php

/**
 * src/Core/Events/Request/RequestContext.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Events\Request
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/Request/RequestContext.php
 */
declare(strict_types=1);

namespace App\Core\Events\Request;

/**
 * Class RequestContext
 * Encapsulates data for a single HTTP request lifecycle.
 *
 * @category  Core
 * @package   App\Core\Events\Request
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class RequestContext
{
    /**
     */
    public function __construct(
        private HttpExchange $httpExchange,
        private RequestMeta $requestMeta
    ) {
        // Empty Constructor
    }

    public function exchange(): HttpExchange
    {
        return $this->httpExchange;
    }

    public function meta(): RequestMeta
    {
        return $this->requestMeta;
    }

    public function path(): string
    {
        return $this->httpExchange->path();
    }

    public function duration(): float
    {
        return $this->requestMeta->duration();
    }
}
