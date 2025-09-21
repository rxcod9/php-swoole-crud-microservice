/**
 * Migration: Create 'items' table
 *
 * This migration creates the 'items' table for storing product information.
 *
 * Columns:
 * - id: Primary key, auto-increment integer.
 * - sku: Unique stock keeping unit, string up to 64 characters.
 * - title: Item title, string up to 150 characters.
 * - price: Item price, decimal with 2 decimal places, defaults to 0.
 * - created_at: Timestamp when the record was created, defaults to current timestamp.
 * - updated_at: Timestamp when the record was last updated, auto-updates on change.
 *
 * Engine: InnoDB
 * Charset: utf8mb4
 */

CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
