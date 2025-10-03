<?php

/**
 * src/Core/Constants.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Constants.php
 */
declare(strict_types=1);

namespace App\Core;

/**
 * Class Constants
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
class Constants
{
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    // PDO Duplicate entry
    public const PDO_INTEGRITY_CONSTRAINT_VIOLATION_SQL_STATE = 23000;

    public const PDO_INTEGRITY_CONSTRAINT_VIOLATION_ERROR_CODE = 1062;

    // Connection Refused
    public const PDO_GENERAL_ERROR_SQL_STATE = 'HY000';

    public const PDO_CONNECTION_REFUSED_ERROR_CODE = 2002;

    public const PDO_CONNECTION_REFUSED_MESSAGE = 'Connection refused';

    public const PDO_CONNECTION_TIMED_OUT_IN = 'Connection timed out in';

    public const PDO_DNS_LOOKUP_RESOLVE_FAILED = 'DNS Lookup resolve failed';

    // MySQL server has gone away
    public const PDO_SERVER_GONE_AWAY_ERROR_CODE = 2006;

    public const PDO_SERVER_GONE_AWAY_MESSAGE = 'MySQL server has gone away';
}
