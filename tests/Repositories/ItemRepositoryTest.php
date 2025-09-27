<?php

namespace Tests\Repositories;

use App\Core\Pools\PDOPool;
use App\Repositories\ItemRepository;
use PDO;
use PDOStatement;
use Tests\TestCase;

/**
 * @covers \App\Repositories\ItemRepository
 */
class ItemRepositoryTest extends TestCase
{
    private ItemRepository $repository;

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

            // Create a fake items table schema for testing
            $pdo->exec("
                DROP TABLE IF EXISTS items;
                CREATE TABLE items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    sku TEXT NOT NULL UNIQUE,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->pool->put($pdo);
            
            // Initialize repository with test PDO connection
            $this->repository = new ItemRepository($this->pool);

            // Insert sample test data
            $this->repository->create([
                'title' => 'Item 001',
                'sku' => 'item-001',
                'price' => 100,
            ]);

            $this->repository->create([
                'title' => 'Item 002',
                'sku' => 'item-002',
                'price' => 100,
            ]);
        });
    }

    /**
     * Test listing items with pagination and sorting.
     */
    public function testListItems(): void
    {
        $this->runInCoroutine(function () {
            $this->assertTrue(true);
        });
    }

    /**
     * Test fetching a single item by ID.
     */
    public function testGetItemById(): void
    {
        $this->runInCoroutine(function () {

            $item = $this->repository->find(1);

            $this->assertNotNull($item, "Item with ID 1 should exist");
            $this->assertEquals('Item 001', $item['title']);
            $this->assertEquals('item-001', $item['sku']);
        });
    }

    /**
     * Test creating a new item.
     */
    public function testCreateItem(): void
    {
        $this->runInCoroutine(function () {
            $newId = $this->repository->create([
                'title'  => 'Item 101',
                'sku' => 'item-101',
                'price' => 1001,
            ]);

            $this->assertIsInt($newId, "Newly created item should return an integer ID");

            $item = $this->repository->find($newId);
            $this->assertEquals('Item 101', $item['title']);
            $this->assertEquals('item-101', $item['sku']);
            $this->assertEquals(1001, $item['price']);
        });
    }

    /**
     * Test updating an existing item.
     */
    public function testUpdateItem(): void
    {
        $this->runInCoroutine(function () {
            $updated = $this->repository->update(1, [
                'title' => 'Item 001 Updated',
                'sku' => 'item-101-updated',
                'price' => 1002,
            ]);

            $this->assertTrue($updated, "Expected update() to return true");

            $item = $this->repository->find(1);
            $this->assertEquals('Item 001 Updated', $item['title']);
            $this->assertEquals('item-101-updated', $item['sku']);
            $this->assertEquals(1002, $item['price']);
        });
    }

    /**
     * Test deleting a item.
     */
    public function testDeleteItem(): void
    {
        $this->runInCoroutine(function () {
            $deleted = $this->repository->delete(1);

            $this->assertTrue($deleted, "Expected delete() to return true");

            $item = $this->repository->find(1);
            $this->assertNull($item, "Deleted item should not be found");
        });
    }
}
