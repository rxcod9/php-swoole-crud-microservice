<?php

/**
 * src/Models/ModelInterface.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Models
 * @package   App\Models
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Models/ModelInterface.php
 */
declare(strict_types=1);

namespace App\Models;

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
interface ModelInterface
{
    /**
     * Hydrate an Model object from a database row array.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self;

    /**
     * Merge an Model object from a request data array.
     *
     * @param array<string, mixed> $data
     */
    public function merge(array $data): self;

    /**
     * Convert the model object to a serializable array (for JSON output).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
