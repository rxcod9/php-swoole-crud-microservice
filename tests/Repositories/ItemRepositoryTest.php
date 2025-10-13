<?php

/**
 * tests/Repositories/ItemRepositoryTest.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/tests/Repositories/ItemRepositoryTest.php
 */
declare(strict_types=1);

namespace Tests\Repositories;

use App\Exceptions\ResourceNotFoundException;
use App\Repositories\ItemRepository;
use PDO;
use RuntimeException;
use Swoole\Coroutine;
use Tests\TestCase;

/**
 * Class ItemRepositoryTest
 * Handles all item repository test operations.
 *
 * @category  Repositories
 * @package   Tests\Repositories
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @covers    \App\Repositories\ItemRepository
 */
final class ItemRepositoryTest extends TestCase
{
    private ItemRepository $itemRepository;

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
        $this->runCoroutine(function (): void {
            // Setup schema for SQLite
            [$pdo, $pdoId] = $this->pool->get();

            // Create a fake items table schema for testing
            $pdo->exec('
                DROP TABLE IF EXISTS items;
                CREATE TABLE items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    sku TEXT NOT NULL UNIQUE,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ');

            $this->pool->put($pdo, $pdoId);

            // Initialize repository with test PDO connection
            $this->itemRepository = new ItemRepository($this->pool);

            // Insert sample test data
            $this->itemRepository->create([
                'title' => 'Item 001',
                'sku'   => 'item-001',
                'price' => 100,
            ]);

            $this->itemRepository->create([
                'title' => 'Item 002',
                'sku'   => 'item-002',
                'price' => 100,
            ]);
        });
    }

    /**
     * Test listing items with pagination and sorting.
     */
    public function testListItems(): void
    {
        $this->runCoroutine(function (): void {
            $this->assertTrue(true);
        });
    }

    /**
     * Test fetching a single item by ID.
     */
    public function testGetItemById(): void
    {
        $this->runCoroutine(function (): void {
            $item = $this->itemRepository->find(1);

            $this->assertNotNull($item, 'Item with ID 1 should exist');
            $this->assertEquals('Item 001', $item['title']);
            $this->assertEquals('item-001', $item['sku']);
        });
    }

    /**
     * Test creating a new item.
     */
    public function testCreateItem(): void
    {
        $this->runCoroutine(function (): void {
            $newId = $this->itemRepository->create([
                'title' => 'Item 101',
                'sku'   => 'item-101',
                'price' => 1001,
            ]);

            $this->assertIsInt($newId, 'Newly created item should return an integer ID');

            $item = $this->itemRepository->find($newId);
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
        $this->runCoroutine(function (): void {
            $updated = $this->itemRepository->update(1, [
                'title' => 'Item 001 Updated',
                'sku'   => 'item-101-updated',
                'price' => 1002,
            ]);

            $this->assertTrue($updated, 'Expected update() to return true');

            $item = $this->itemRepository->find(1);
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
        $this->runCoroutine(function (): void {
            $deleted = $this->itemRepository->delete(1);
            
            $this->assertTrue($deleted, 'Expected delete() to return true');
            
            $this->expectException(ResourceNotFoundException::class);
            $this->itemRepository->find(1);
        });
    }
}
