<?php

use Swoole\Coroutine\MySQL;

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

function logMsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

Swoole\Runtime::enableCoroutine();

go(function () {
    logMsg("Starting migration...");

    $cfg = require __DIR__ . "/../config/config.php";
    logMsg("Loaded config.");

    $db  = $cfg["db"]["mysql"];
    logMsg("Database config: host={$db['host']}, user={$db['user']}, db={$db['db']}, port={$db['port']}");

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

    $migrations = require __DIR__ . "/../config/database.php";
    logMsg("Loaded migrations config.");

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
