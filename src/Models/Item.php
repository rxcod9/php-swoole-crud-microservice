<?php

/**
 * src/Models/Item.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Models
 * @package   App\Models
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Models/Item.php
 */
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

/**
 * Represents a single Item entity in the model layer.
 *
 * @category  Models
 * @package   App\Models
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 */
class Item extends Model
{
    public const TAG = 'Item';

    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $title,
        public readonly float $price,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * Hydrate an Item object from a database row array.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'row: ' . var_export($row, true));

        return new self(
            id: (int)$row['id'],
            sku: (string)$row['sku'],
            title: (string)$row['title'],
            price: (float)$row['price'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    /**
     * Convert the model object to a serializable array (for JSON output).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'sku'        => $this->sku,
            'title'      => $this->title,
            'price'      => number_format($this->price, 2, '.', ''),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
