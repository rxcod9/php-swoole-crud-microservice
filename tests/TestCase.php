<?php

/**
 * tests/TestCase.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/tests/TestCase.php
 */
declare(strict_types=1);

namespace Tests;

use App\Core\Pools\PDOPool;
use PDO;
use Swoole\Coroutine;

/**
 * Class TestCase
 * Handles all test case operations.
 *
 * @category  General
 * @package   Tests
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @covers    \App\Repositories\Base
 */
abstract class TestCase extends CoroutineTestCase
{
    protected PDOPool $pool;

    /**
     * Set up an in-memory SQLite database for testing.
     *
     * This avoids needing a real MySQL connection while testing.
     * PDO is used the same way in production, so this keeps tests realistic.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset schema before each test
        // Coroutine\run(function () {
        // In-memory SQLite database (fast and isolated for tests)
        $this->runCoroutine(function (): void {
            $this->pool = $this->createPool();
            $this->pool->init();
        });
    }

    /**
     * Shared PDOPool for tests.
     *
     * Each test case can retrieve this via dependency injection instead of globals.
     */
    protected function createPool(): PDOPool
    {
        $dsn  = getenv('DB_DSN') ?: 'sqlite:/app/database/testing.db';
        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PASS') ?: null;

        $pdoPool = new PDOPool([
            'dsn'      => $dsn,
            'user'     => $user,
            'password' => $pass,
            'options'  => [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT               => false, // we manage pool manually
            ],
            'size' => 10,
        ]);

        $pdoPool->init();

        return $pdoPool;
    }

    /**
     * This method is called after each test.
     *
     * @codeCoverageIgnore
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // only handle SQLite
        $dsn = getenv('DB_DSN') ?: 'sqlite:/app/database/testing.db';
        if (isset($dsn) && str_starts_with($dsn, 'sqlite:')) {
            $dbFile = substr($dsn, 7); // remove "sqlite:"

            // if it's not in-memory
            if ($dbFile !== ':memory:' && file_exists($dbFile)) {
                unlink($dbFile); // delete old file so we start fresh
            }
        }
    }
}
