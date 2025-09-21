<?php

use Swoole\Coroutine\MySQL;

/**
 * Logs a message with a timestamp.
 *
 * @param string $msg The message to log.
 * @return void
 */
function logMsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

// Enable Swoole coroutine runtime for asynchronous operations.
Swoole\Runtime::enableCoroutine();

/**
 * Main coroutine for running database migrations.
 *
 * Loads configuration, connects to MySQL, and executes migration SQL files.
 *
 * @return void
 */
go(function () {
    logMsg("Starting migration...");

    // Load application configuration.
    $cfg = require __DIR__ . "/../config/config.php";
    logMsg("Loaded config.");

    // Extract MySQL database configuration.
    $db  = $cfg["db"]["mysql"];
    logMsg("Database config: host={$db['host']}, user={$db['user']}, db={$db['db']}, port={$db['port']}");

    // Create a new Swoole Coroutine MySQL client and connect.
    $mysql = new MySQL();
    $res = $mysql->connect([
        'host'     => $db['host'],
        'user'     => $db['user'],
        'password' => $db['pass'],
        'database' => $db['db'],
        'port'     => $db['port'],
    ]);
    if ($res === false) {
        logMsg("MySQL connect error: " . $mysql->connect_error);
        exit(1);
    }
    logMsg("Connected to MySQL.");

    // Load migration file paths from configuration.
    $migrations = require __DIR__ . "/../config/database.php";
    logMsg("Loaded migrations config.");

    // Iterate over migration groups and run each migration SQL file.
    foreach ($migrations as $k => $migs) {
        logMsg("Processing migration group: $k");
        foreach ($migs as $m) {
            logMsg("Running migration: $m");
            $sql = file_get_contents($m);
            if ($sql === false) {
                logMsg("Error reading migration file: $m");
                exit(1);
            }
            $result = $mysql->query($sql);
            if ($result === false) {
                logMsg("Error running $m: " . $mysql->error);
                exit(1);
            }
            logMsg("Migration succeeded: $m");
        }
    }

    logMsg("All migrations completed.");
});
