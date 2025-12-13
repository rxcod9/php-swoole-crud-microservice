<?php

/**
 * src/Core/Events/RequestMeta.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestMeta.php
 */
declare(strict_types=1);

namespace App\Core\Events\Request;

/**
 * Request metadata wrapper — keeps tracking info like request ID, action, and start time.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final class RequestMeta
{
    public function __construct(
        private readonly string $reqId,
        private readonly float $start
    ) {
        // Empty Constructor
    }

    public function id(): string
    {
        return $this->reqId;
    }

    public function duration(): float
    {
        return microtime(true) - $this->start;
    }
}
