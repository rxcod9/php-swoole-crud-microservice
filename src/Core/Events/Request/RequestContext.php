<?php

/**
 * src/Core/Events/Request/RequestContext.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
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

use Swoole\Http\Request;
use Swoole\Http\Response;

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
     * @param Request  $request  Incoming Swoole HTTP request
     * @param Response $response Swoole HTTP response
     * @param string   $reqId    Unique request ID
     * @param float    $start    Request start timestamp (for metrics)
     */
    public function __construct(
        public Request $request,
        public Response $response,
        public string $reqId,
        public float $start
    ) {
        //
    }
}
