<?php

/**
 * src/Tables/BaseTableProxy.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: Base proxy class for dynamic interaction with Swoole\Table.
 * PHP version 8.5
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.1.0
 * @since     2025-10-26
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/BaseTableProxy.php
 */
declare(strict_types=1);

namespace App\Tables;

use BadMethodCallException;
use LogicException;
use OutOfBoundsException;
use Swoole\Table;

/**
 * Class BaseTableProxy
 * Acts as a dynamic proxy for Swoole\Table, providing:
 * - Transparent method forwarding
 * - Property get/set interception
 * - Key normalization for Swoole\Table compliance
 * All Swoole table wrappers should extend this class.
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-26
 */
abstract class BaseTableProxy
{
    /**
     * Constructor
     *
     * @param Table $table The underlying Swoole\Table instance to proxy
     */
    public function __construct(protected Table $table)
    {
        // Store reference to Swoole\Table; nothing else needed here
    }

    /**
     * Forward dynamic method calls to the proxied Swoole\Table.
     *
     * @param string           $name      Method name being called
     * @param array<int,mixed> $arguments Arguments to pass to Swoole\Table
     * @return mixed Returns result from Swoole\Table method
     *
     * @throws BadMethodCallException If method does not exist or is not allowed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return match ($name) {
            'column'        => $this->table->column(...$arguments),
            'create'        => $this->table->create(...$arguments),
            'destroy'       => $this->table->destroy(...$arguments),
            'set'           => $this->table->set(...$arguments),
            'get'           => $this->table->get(...$arguments),
            'count'         => $this->table->count(...$arguments),
            'del'           => $this->table->del(...$arguments),
            'delete'        => $this->table->delete(...$arguments),
            'exists'        => $this->table->exists(...$arguments),
            'exist'         => $this->table->exist(...$arguments),
            'incr'          => $this->table->incr(...$arguments),
            'decr'          => $this->table->decr(...$arguments),
            'getSize'       => $this->table->getSize(...$arguments),
            'getMemorySize' => $this->table->getMemorySize(...$arguments),
            'stats'         => $this->table->stats(...$arguments),
            'rewind'        => $this->table->rewind(...$arguments),
            'valid'         => $this->table->valid(...$arguments),
            'next'          => $this->table->next(...$arguments),
            'current'       => $this->table->current(...$arguments),
            'key'           => $this->table->key(...$arguments),
            default         => throw new BadMethodCallException(
                sprintf('Method %s does not exist or cannot be proxied on Swoole\Table', $name)
            ),
        };
    }

    /**
     * Magic getter for Swoole\Table properties.
     *
     * @param string $name Property name being accessed
     * @return mixed Value of the property
     *
     * @throws OutOfBoundsException If property does not exist
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'size'       => $this->table->size,
            'memorySize' => $this->table->memorySize,
            default      => throw new OutOfBoundsException(
                sprintf('Property "%s" not found on Swoole\Table', $name)
            )
        };
    }

    /**
     * Magic setter for Swoole\Table properties.
     *
     * @param string $name  Property name being set
     * @param mixed  $value Value to assign
     *
     * @throws OutOfBoundsException If property does not exist
     * @throws LogicException       If attempting to unset or mutate immutable Swoole internal properties
     */
    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'size', 'memorySize' => throw new LogicException(
                sprintf('Property "%s" on Swoole\Table cannot be set', $name)
            ),
            default => throw new OutOfBoundsException(
                sprintf('Property "%s" not found on Swoole\Table', $name)
            )
        };
    }

    /**
     * Normalize a table key for Swoole\Table compliance.
     *
     * Max key length: 56 characters
     *
     * @param string $key Original key
     * @return string Normalized key
     */
    protected function normalizeKey(string $key): string
    {
        return strlen($key) > 56 ? substr($key, 0, 56) : $key;
    }
}
