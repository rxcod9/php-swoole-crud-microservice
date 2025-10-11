<?php

/**
 * src/Core/Messages.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Messages.php
 */
declare(strict_types=1);

namespace App\Core;

/**
 * Class Messages
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
class Messages
{
    public const ERROR_INTERNAL_ERROR = 'An internal error occurred. Please try again later.';

    public const ROUTE_NOT_FOUND = 'Route not found.';

    public const RESOURCE_NOT_FOUND = 'Resource %s not found.';

    public const PDO_EXCEPTION_MESSAGE = '[%s:%d] pdoId #%s - Code: %s | PDOException: %s';

    public const PDO_EXCEPTION_FINALLY_MESSAGE = '[%s:%d] pdoId #%s - finally called | PDO errorCode: %s | errorInfo: %s';

    public const CREATE_FAILED = 'Unable to create. Please try again later.';
}
