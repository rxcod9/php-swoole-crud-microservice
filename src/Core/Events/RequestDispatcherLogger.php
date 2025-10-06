<?php

/**
 * src/Core/Events/RequestDispatcherLogger.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestDispatcherLogger.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestLogContext;

/**
 * Class RequestDispatcherLogger
 * Handles all request dispatcher logger operations.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestDispatcherLogger
{
    public function log(RequestLogContext $requestLogContext): void
    {
        $entry = [
            'level'    => $requestLogContext->identity->level,
            'request'  => $requestLogContext->identity->requestContext->reqId,
            'duration' => $requestLogContext->metrics->duration,
            'data'     => $requestLogContext->metrics->payload,
        ];

        error_log(json_encode($entry));
    }
}
