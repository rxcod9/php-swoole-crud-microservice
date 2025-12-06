<?php

/**
 * src/Core/Events/HttpExchange.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/HttpExchange.php
 */
declare(strict_types=1);

namespace App\Core\Events\Request;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Wraps request-response pair for a single HTTP exchange.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final class HttpExchange
{
    public function __construct(
        private readonly Request $request,
        private readonly Response $response
    ) {
        //
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function path(): string
    {
        return $this->request->getPath();
    }
}
