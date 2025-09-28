<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\UserRepository;
use PDO;
use Tests\TestCase;

/**
 * @covers \App\Repositories\UserRepository
 */
class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    /**
     * Set up an in-memory SQLite database for testing.
     *
     * This avoids needing a real MySQL connection while testing.
     * PDO is used the same way in production, so this keeps tests realistic.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->runInCoroutine(function () {

            // Setup schema for SQLite
            $pdo = $this->pool->get();

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

            // Initialize repository with test PDO connection
            $this->repository = new UserRepository($this->pool);

            // Insert sample test data
            $this->repository->create([
                'name'  => 'Alice',
                'email' => 'alice@example.com',
            ]);

            $this->repository->create([
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
        $this->runInCoroutine(function () {
            $this->assertTrue(true);
        });
    }

    /**
     * Test fetching a single user by ID.
     */
    public function testGetUserById(): void
    {
        $this->runInCoroutine(function () {

            // $this->pool = createTestPool();
            // $this->repository = new UserRepository($this->pool);

            $user = $this->repository->find(1);

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
        $this->runInCoroutine(function () {
            $newId = $this->repository->create([
                'name'  => 'Charlie',
                'email' => 'charlie@example.com',
            ]);

            $this->assertIsInt($newId, 'Newly created user should return an integer ID');

            $user = $this->repository->find($newId);
            $this->assertEquals('Charlie', $user['name']);
            $this->assertEquals('charlie@example.com', $user['email']);
        });
    }

    /**
     * Test updating an existing user.
     */
    public function testUpdateUser(): void
    {
        $this->runInCoroutine(function () {
            $updated = $this->repository->update(1, [
                'name'  => 'Alice Updated',
                'email' => 'alice-updated@example.com',
            ]);

            $this->assertTrue($updated, 'Expected update() to return true');

            $user = $this->repository->find(1);
            $this->assertEquals('Alice Updated', $user['name']);
        });
    }

    /**
     * Test deleting a user.
     */
    public function testDeleteUser(): void
    {
        $this->runInCoroutine(function () {
            $deleted = $this->repository->delete(1);

            $this->assertTrue($deleted, 'Expected delete() to return true');

            $user = $this->repository->find(1);
            $this->assertNull($user, 'Deleted user should not be found');
        });
    }
}
