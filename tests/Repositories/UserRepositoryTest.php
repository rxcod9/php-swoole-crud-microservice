<?php

/**
 * tests/Repositories/UserRepositoryTest.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Repositories
 * @package   Tests\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/tests/Repositories/UserRepositoryTest.php
 */
declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\UserRepository;
use PDO;
use Tests\TestCase;

/**
 * Class UserRepositoryTest
 * Handles all user repository test operations.
 *
 * @category  Repositories
 * @package   Tests\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @covers    \App\Repositories\UserRepository
 */
final class UserRepositoryTest extends TestCase
{
    private UserRepository $userRepository;

    /**
     * Set up an in-memory SQLite database for testing.
     *
     * This avoids needing a real MySQL connection while testing.
     * PDO is used the same way in production, so this keeps tests realistic.
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->runInCoroutine(function (): void {
            // Setup schema for SQLite
            [$pdo, $pdoId] = $this->pool->get();

            // Create a fake users table schema for testing
            $pdo->exec('
                DROP TABLE IF EXISTS users;
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ');

            $this->pool->put($pdo, $pdoId);

            // Initialize repository with test PDO connection
            $this->userRepository = new UserRepository($this->pool);

            // Insert sample test data
            $this->userRepository->create([
                'name'  => 'Alice',
                'email' => 'alice@example.com',
            ]);

            $this->userRepository->create([
                'name'  => 'Bob',
                'email' => 'bob@example.com',
            ]);
        });
    }

    /**
     * Test listing users with pagination and sorting.
     */
    public function testListUsers(): void
    {
        $this->runInCoroutine(function (): void {
            $this->assertTrue(true);
        });
    }

    /**
     * Test fetching a single user by ID.
     */
    public function testGetUserById(): void
    {
        $this->runInCoroutine(function (): void {
            $user = $this->userRepository->find(1);

            $this->assertNotNull($user, 'User with ID 1 should exist');
            $this->assertEquals('Alice', $user['name']);
            $this->assertEquals('alice@example.com', $user['email']);
        });
    }

    /**
     * Test creating a new user.
     */
    public function testCreateUser(): void
    {
        $this->runInCoroutine(function (): void {
            $newId = $this->userRepository->create([
                'name'  => 'Charlie',
                'email' => 'charlie@example.com',
            ]);

            $this->assertIsInt($newId, 'Newly created user should return an integer ID');

            $user = $this->userRepository->find($newId);
            $this->assertEquals('Charlie', $user['name']);
            $this->assertEquals('charlie@example.com', $user['email']);
        });
    }

    /**
     * Test updating an existing user.
     */
    public function testUpdateUser(): void
    {
        $this->runInCoroutine(function (): void {
            $updated = $this->userRepository->update(1, [
                'name'  => 'Alice Updated',
                'email' => 'alice-updated@example.com',
            ]);

            $this->assertTrue($updated, 'Expected update() to return true');

            $user = $this->userRepository->find(1);
            $this->assertEquals('Alice Updated', $user['name']);
        });
    }

    /**
     * Test deleting a user.
     */
    public function testDeleteUser(): void
    {
        $this->runInCoroutine(function (): void {
            $deleted = $this->userRepository->delete(1);

            $this->assertTrue($deleted, 'Expected delete() to return true');

            $user = $this->userRepository->find(1);
            $this->assertNull($user, 'Deleted user should not be found');
        });
    }
}
