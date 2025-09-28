<?php

declare(strict_types=1);

namespace App\Core;

class Constants
{
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';


    // PDO Duplicate entry
    public const PDO_INTEGRITY_CONSTRAINT_VIOLATION_SQL_STATE  = 23000;
    public const PDO_INTEGRITY_CONSTRAINT_VIOLATION_ERROR_CODE = 1062;

    // Connection Refused
    public const PDO_GENERAL_ERROR_SQL_STATE       = 'HY000';
    public const PDO_CONNECTION_REFUSED_ERROR_CODE = 2002;
    public const PDO_CONNECTION_REFUSED_MESSAGE    = 'Connection refused';

    // MySQL server has gone away
    public const PDO_SERVER_GONE_AWAY_ERROR_CODE = 2006;
    public const PDO_SERVER_GONE_AWAY_MESSAGE    = 'MySQL server has gone awa';
}
