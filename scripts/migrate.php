<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Constants;
use Dotenv\Dotenv;

// Load .env file from project root
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Optional: validate required env variables
$dotenv->required([
    // 'APP_ENV',
    // 'APP_DEBUG',
    // 'SWOOLE_WORKER_NUM',
    // 'SWOOLE_TASK_WORKER_NUM',
    // 'SWOOLE_MAX_REQUEST',
    // 'SSL_ENABLE',
    'DB_DRIVER',
    'DB_HOST',
    'DB_DSN',
    'DB_USER',
    'DB_PASS',
    'DB_DATABASE',
    // 'DB_CHARSET',
    // 'DB_TIMEOUT',
    // 'DB_POOL_MIN',
    // 'DB_POOL_MAX',
    'REDIS_HOST',
    'REDIS_PORT',
    // 'REDIS_PASSWORD',
    // 'REDIS_DATABASE',
    // 'REDIS_POOL_MIN',
    // 'REDIS_POOL_MAX',
    // 'QUEUE_MAX_PENDING',
])->notEmpty();

/**
 * Logs a message with a timestamp.
 *
 * @param string $msg The message to log.
 */
function logMsg(string $msg): void
{
    error_log('[' . date(Constants::DATETIME_FORMAT) . "] $msg" . PHP_EOL);
}

/**
 * Main function for running database migrations.
 *
 * Loads configuration, connects to MySQL using PDO, and executes migration SQL files.
 *
 */
function runMigrations(): void
{
    logMsg('Starting migration...');

    // Load application configuration
    $cfg = require_once __DIR__ . '/../config/config.php';
    logMsg('Loaded config.');

    // Extract MySQL database configuration
    $db = $cfg['db'][$cfg['db']['driver']];
    logMsg("Database config: host={$db['host']}, user={$db['user']}, db={$db['db']}, port={$db['port']}");

    // Create a PDO connection
    try {
        $dsn = \sprintf(
            $db['dsn'] ?? 'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? 3306,
            $db['db'] ?? 'app'
        );
        $pdo = new PDO(
            $dsn,
            $db['user'],
            $db['pass'],
            [
                PDO::ATTR_ERRMODE                   => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE        => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES          => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY  => false,
            ]
        );
    } catch (PDOException $e) {
        logMsg('PDO connection error: ' . $e->getMessage());
        exit(1);
    }
    logMsg('Connected to MySQL via PDO.');

    // Load migration files configuration
    $migrations = require_once __DIR__ . '/../config/database.php';
    logMsg('Loaded migrations config.');

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

    logMsg('All migrations completed.');
}

// Run migrations
runMigrations();
