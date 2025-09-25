<?php

/**
 * Logs a message with a timestamp.
 *
 * @param string $msg The message to log.
 * @return void
 */
function logMsg(string $msg): void
{
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

/**
 * Main function for running database migrations.
 *
 * Loads configuration, connects to MySQL using PDO, and executes migration SQL files.
 *
 * @return void
 */
function runMigrations(): void
{
    logMsg("Starting migration...");

    // Load application configuration
    $cfg = require __DIR__ . "/../config/config.php";
    logMsg("Loaded config.");

    // Extract MySQL database configuration
    $db = $cfg["db"][$cfg["db"]["driver"]];
    logMsg("Database config: host={$db['host']}, user={$db['user']}, db={$db['db']}, port={$db['port']}");

    // Create a PDO connection
    try {
        $dsn = sprintf(
            $db['dsn'] ?? "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? 3306,
            $db['db'] ?? 'app'
        );
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        logMsg("PDO connection error: " . $e->getMessage());
        exit(1);
    }
    logMsg("Connected to MySQL via PDO.");

    // Load migration files configuration
    $migrations = require __DIR__ . "/../config/database.php";
    logMsg("Loaded migrations config.");

    // Execute each migration
    foreach ($migrations as $group => $files) {
        logMsg("Processing migration group: $group");

        foreach ($files as $file) {
            logMsg("Running migration: $file");

            $sql = file_get_contents($file);
            if ($sql === false) {
                logMsg("Error reading migration file: $file");
                exit(1);
            }

            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                logMsg("Error running $file: " . $e->getMessage());
                exit(1);
            }

            logMsg("Migration succeeded: $file");
        }
    }

    logMsg("All migrations completed.");
}

// Run migrations
runMigrations();
