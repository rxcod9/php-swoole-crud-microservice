<?php

/**
 * tests/CoroutineTestCase.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  General
 * @package   Tests
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/tests/CoroutineTestCase.php
 */
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Swoole\Coroutine;
use Throwable;

/**
 * Class CoroutineTestCase
 * Handles all test case operations.
 *
 * @category  General
 * @package   Tests
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
abstract class CoroutineTestCase extends BaseTestCase
{
    protected function runCoroutine(callable $fn): void
    {
        $exception = null;
        Coroutine\run(function () use ($fn, &$exception): void {
            try {
                $fn();
            } catch (Throwable $e) {
                $exception = $e;
            }
        });
        if ($exception !== null) {
            throw $exception;
        }
    }
}
