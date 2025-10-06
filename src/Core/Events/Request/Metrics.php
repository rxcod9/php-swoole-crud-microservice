<?php

/**
 * src/Core/Events/Request/Metrics.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/Request/Metrics.php
 */
declare(strict_types=1);

namespace App\Core\Events\Request;

/**
 * Encapsulates request metrics for logging.
 *
 * @category  Core
 * @package   App\Core\Events\Request
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class Metrics
{
    public function __construct(
        public array $payload,
        public float $duration
    ) {
    }
}
