-- --------------------------------------------------------
-- Migration: 001_users.sql
-- Description: Creates the 'users' table for storing user information.
-- Fields:
--   id         : Primary key, auto-incremented integer.
--   name       : User's name, up to 100 characters, required.
--   email      : User's email, up to 150 characters, required, unique.
--   created_at : Timestamp of record creation, defaults to current time.
--   updated_at : Timestamp of last update, auto-updated on modification.
-- Engine: InnoDB
-- Charset: utf8mb4
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
