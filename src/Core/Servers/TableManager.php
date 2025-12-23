<?php

/**
 * src/Core/Servers/TableManager.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/TableManager.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Tables\TableWithLRUAndGC;
use Swoole\Table;

/**
 * Class TableManager
 * Handles all table operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class TableManager
{
    public Table $healthTable;

    /** @var TableWithLRUAndGC<string, array<string, mixed>> */
    public TableWithLRUAndGC $lruTable;

    /** @var TableWithLRUAndGC<string, array<string, mixed>> */
    public TableWithLRUAndGC $rateLimitTable;

    public function __construct()
    {
        $this->initHealthTable();
        $this->initLRUTable();
        $this->initRateLimitTable();
    }

    private function initHealthTable(): void
    {
        $this->healthTable = new Table(64);
        foreach (['pid', 'timer_id', 'first_heartbeat', 'last_heartbeat', 'mysql_capacity', 'mysql_available', 'mysql_created', 'mysql_in_use', 'redis_capacity', 'redis_available', 'redis_created', 'redis_in_use'] as $column) {
            $this->healthTable->column($column, Table::TYPE_INT, 10);
        }

        $this->healthTable->create();
    }

    private function initLRUTable(): void
    {
        $this->lruTable = new TableWithLRUAndGC(8192, 600);
        $this->lruTable->create();
    }

    private function initRateLimitTable(): void
    {
        $this->rateLimitTable = new TableWithLRUAndGC(60);
        $this->rateLimitTable->create();
    }
}
