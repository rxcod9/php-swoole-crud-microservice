<?php

/**
 * src/Support/PdoHelper.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PDO-specific retry and connection error detection logic with enhanced debug logging.
 * PHP version 8.4
 *
 * @category  Support
 * @package   App\Support
 * @author    Ram
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-25
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/PdoHelper.php
 */
declare(strict_types=1);

namespace App\Support;

use App\Core\Constants;
use PDOException;

/**
 * Class PdoHelper
 * Handles PDO-specific retry and connection error detection logic.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-25
 */
class PdoHelper
{
    /**
     * Determines whether PDO exception should trigger retry.
     *
     */
    public static function shouldRetry(PDOException $pdoException): bool
    {
        logDebug(__METHOD__, 'called', [
            'message'   => $pdoException->getMessage(),
            'code'      => $pdoException->getCode(),
            'file'      => $pdoException->getFile(),
            'line'      => $pdoException->getLine(),
            'errorInfo' => $pdoException->errorInfo ?? null,
        ]);

        if (self::isConnectionRefused($pdoException)) {
            logDebug(__METHOD__, 'connection_refused_detected', [
                'retry' => true,
            ]);
            return true;
        }

        if (self::isServerGoneAway($pdoException)) {
            logDebug(__METHOD__, 'server_gone_away_detected', [
                'retry' => true,
            ]);
            return true;
        }

        logDebug(__METHOD__, 'no_retry_condition_met', [
            'retry' => false,
        ]);

        return false;
    }

    /**
     * Check if PDO error represents a connection refused.
     *
     */
    public static function isConnectionRefused(PDOException $pdoException): bool
    {
        $info       = $pdoException->errorInfo ?? [];
        $sqlState   = $info[0] ?? null;
        $driverCode = $info[1] ?? null;
        $driverMsg  = $info[2] ?? '';

        $match = (
            $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
            && $driverCode === Constants::PDO_CONNECTION_REFUSED_ERROR_CODE
            && (
                str_contains($driverMsg, Constants::PDO_CONNECTION_REFUSED_MESSAGE)
                || str_contains($driverMsg, Constants::PDO_CONNECTION_TIMED_OUT_IN)
                || str_contains($driverMsg, Constants::PDO_DNS_LOOKUP_RESOLVE_FAILED)
            )
        );

        logDebug(__METHOD__, 'connection_refused_check', [
            'sqlState'   => $sqlState,
            'driverCode' => $driverCode,
            'driverMsg'  => $driverMsg,
            'match'      => $match,
            'criteria'   => [
                'expected_sqlState'   => Constants::PDO_GENERAL_ERROR_SQL_STATE,
                'expected_driverCode' => Constants::PDO_CONNECTION_REFUSED_ERROR_CODE,
            ],
        ]);

        return $match;
    }

    /**
     * Check if PDO error represents a "server gone away" issue.
     *
     */
    public static function isServerGoneAway(PDOException $pdoException): bool
    {
        $info       = $pdoException->errorInfo ?? [];
        $sqlState   = $info[0] ?? null;
        $driverCode = $info[1] ?? null;
        $driverMsg  = $info[2] ?? '';

        $match = (
            $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
            && $driverCode === Constants::PDO_SERVER_GONE_AWAY_ERROR_CODE
            && str_contains($driverMsg, Constants::PDO_SERVER_GONE_AWAY_MESSAGE)
        );

        logDebug(__METHOD__, 'server_gone_away_check', [
            'sqlState'   => $sqlState,
            'driverCode' => $driverCode,
            'driverMsg'  => $driverMsg,
            'match'      => $match,
            'criteria'   => [
                'expected_sqlState'   => Constants::PDO_GENERAL_ERROR_SQL_STATE,
                'expected_driverCode' => Constants::PDO_SERVER_GONE_AWAY_ERROR_CODE,
            ],
        ]);

        return $match;
    }
}
