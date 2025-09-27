<?php

namespace Tests;

use App\Core\Pools\PDOPool;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Swoole\Coroutine;

/**
 * @covers \App\Repositories\Base
 */
abstract class TestCase extends BaseTestCase
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
        $this->runInCoroutine(function () {
            $this->pool = $this->createPool();
        });
    }

    /**
     * Shared PDOPool for tests.
     *
     * Each test case can retrieve this via dependency injection instead of globals.
     */
    protected function createPool(): PDOPool
    {
        $dsn  = getenv('DB_DSN') ?: 'sqlite:database.sqlite';
        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PASS') ?: null;

        return new PDOPool([
            'dsn'      => $dsn,
            'user'     => $user,
            'password' => $pass,
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
            'size' => 1,
        ]);
    }

    protected function runInCoroutine(callable $fn): void
    {
        Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

        Coroutine\run(function () use ($fn) {
            $fn();
        });
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
        $dsn  = getenv('DB_DSN') ?: 'sqlite:database.sqlite';
        if (isset($dsn) && str_starts_with($dsn, 'sqlite:')) {
            $dbFile = substr($dsn, 7); // remove "sqlite:"
            
            // if it's not in-memory
            if ($dbFile !== ':memory:' && file_exists($dbFile)) {
                unlink($dbFile); // delete old file so we start fresh
            }
        }

    }
}
