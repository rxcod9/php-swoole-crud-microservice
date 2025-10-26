<?php

/**
 * src/Models/User.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Models/User.php
 */
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Exception;

/**
 * Represents a single User entity in the model layer.
 *
 * @category  Models
 * @package   App\Models
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 */
class User extends Model
{
    public const TAG = 'User';

    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {
        // Empty constructor
    }

    /**
     * Hydrate an User object from a database row array.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'row: ' . var_export($row, true));

        try {
            if (
                $row['created_at'] === null ||
                $row['updated_at'] === null
            ) {
                throw new Exception('Empty dates');
            }

            return new self(
                id: (int)$row['id'],
                email: (string)$row['email'],
                name: (string)$row['name'],
                createdAt: new DateTimeImmutable($row['created_at']),
                updatedAt: new DateTimeImmutable($row['updated_at']),
            );
        } catch (Exception $exception) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, $exception->getMessage() . ' row: ' . var_export($row, true));
            throw $exception;
        }
    }

    /**
     * Merge an User object from a request data array.
     *
     * @param array<string, mixed> $data
     */
    public function merge(array $data): self
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'data: ' . var_export($data, true));

        $this->email = (string)$data['email'];
        $this->name  = (string)$data['name'];

        return $this;
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
            'email'      => $this->email,
            'name'       => $this->name,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
