<?php

/**
 * src/Support/RetryHelper.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: Generic retry logic for transient failures with enhanced debug logging.
 * PHP version 8.4
 *
 * @category  Support
 * @package   App\Support
 * @author    Ram
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-25
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/RetryHelper.php
 */
declare(strict_types=1);

namespace App\Support;

use App\Exceptions\CreateFailedException;
use App\Exceptions\QueryFailedException;
use App\Exceptions\ResourceNotFoundException;
use PDOException;
use Throwable;

/**
 * Class RetryHelper
 * Generic retry logic for transient failures.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-25
 */
class RetryHelper
{
    /**
     * Determine if the given Throwable should be retried.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public static function shouldRetry(Throwable $throwable): bool
    {
        logDebug(__METHOD__, 'called', [
            'exception_class' => get_class($throwable),
            'message'         => $throwable->getMessage(),
            'code'            => $throwable->getCode(),
            'file'            => $throwable->getFile(),
            'line'            => $throwable->getLine(),
        ]);

        // 1️⃣ Retry on known retryable exception classes
        if ($throwable instanceof CreateFailedException || $throwable instanceof QueryFailedException) {
            logDebug(__METHOD__, 'matched_retryable_class', [
                'class' => get_class($throwable),
                'retry' => true,
            ]);
            return true;
        }

        // 2️⃣ Retry if it's a PDOException recognized by PdoHelper
        if ($throwable instanceof PDOException && PdoHelper::shouldRetry($throwable)) {
            logDebug(__METHOD__, 'pdo_exception_check', [
                'class' => get_class($throwable),
                'retry' => true,
            ]);
            return true;
        }

        // 3️⃣ Retry if message matches transient failure pattern
        $patternRetry = self::matchesRetryPattern($throwable->getMessage());
        logDebug(__METHOD__, 'pattern_retry_check', [
            'message' => $throwable->getMessage(),
            'retry'   => $patternRetry,
        ]);
        return $patternRetry;
    }

    /**
     * Determine if the given Throwable should be force retried (used for eventual consistency).
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public static function shouldForceRetry(Throwable $throwable): bool
    {
        logDebug(__METHOD__, 'called', [
            'exception_class' => get_class($throwable),
            'message'         => $throwable->getMessage(),
            'code'            => $throwable->getCode(),
        ]);

        // 1️⃣ Always force retry for ResourceNotFoundException
        if ($throwable instanceof ResourceNotFoundException) {
            logDebug(__METHOD__, 'matched_force_retryable_class', [
                'class' => get_class($throwable),
                'retry' => true,
            ]);
            return true;
        }

        // 2️⃣ Fallback to shouldRetry() for other transient failures
        $shouldRetry = self::shouldRetry($throwable);
        logDebug(__METHOD__, 'fallback_shouldRetry_result', [
            'retry' => $shouldRetry,
        ]);
        return $shouldRetry;
    }

    /**
     * Detects retryable message patterns like timeout/deadlock.
     *
     */
    public static function matchesRetryPattern(string $msg): bool
    {
        logDebug(__METHOD__, 'called', [
            'message' => $msg,
        ]);

        $patterns = [
            '/deadlock/i',
            '/timeout/i',
            '/connection.*refused/i',
            '/temporarily.*unavailable/i',
            '/lost.*connection/i',
            '/Query.*failed/i',
            '/Empty.*dates/i',
        ];

        foreach ($patterns as $pattern) {
            try {
                if (preg_match($pattern, $msg)) {
                    logDebug(__METHOD__, 'matched_retryable_pattern', [
                        'pattern' => $pattern,
                        'message' => $msg,
                    ]);
                    return true;
                }
            } catch (Throwable $e) {
                logDebug(__METHOD__, 'regex_error', [
                    'pattern' => $pattern,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            }
        }

        logDebug(__METHOD__, 'no_pattern_match_found', [
            'retry' => false,
        ]);

        return false;
    }
}
