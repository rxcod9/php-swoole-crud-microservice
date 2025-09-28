<?php

declare(strict_types=1);
/**
 * Database configuration file.
 *
 * This file returns an array containing the paths to SQL migration files.
 * These migrations are used to set up and update the database schema.
 *
 * @return array{
 *     migrations: string[]
 * }
 */

return [
  'migrations' => [
    __DIR__ . '/../migrations/001_users.sql', // Migration for users table
    __DIR__ . '/../migrations/002_items.sql', // Migration for items table
  ],
];
