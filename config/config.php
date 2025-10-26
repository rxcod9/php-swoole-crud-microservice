<?php

declare(strict_types=1);

/**
 * Application configuration file for php-swoole-crud-microservice.
 *
 * This file returns an array containing configuration settings for the application,
 * server, database, Redis, and queue. Environment variables are used where available,
 * with sensible defaults provided for local development.
 *
 * @package    App
 * @subpackage Config
 * @author     Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license    MIT
 * @version    1.0.0
 * @see        https://github.com/your-repo/php-swoole-crud-microservice
 */

return [
    /**
     * Application settings.
     *
     * @var array
     */
    'app' => [

        /**
         * Application name.
         *
         * @var string
         */
        'name' => env('APP_NAME', 'php-swoole-crud-microservice'),

        /**
         * Application environment (e.g., local, production).
         *
         * @var string
         */
        'env' => env('APP_ENV', 'production'),

        /**
         * Enable debug mode.
         *
         * @var bool
         */
        'debug' => (bool)(env('APP_DEBUG', false)),

        /**
         * Default timezone.
         *
         * @var string
         */
        'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    ],

    /**
     * Swoole server settings.
     *
     * @var array
     */
    'server' => [
        /**
         * Server host.
         *
         * @var string
         */
        'host' => '0.0.0.0',

        /**
         * HTTP server port.
         *
         * @var int
         */
        'http_port' => 9501,

        /**
         * WebSocket server port.
         *
         * @var int
         */
        'ws_port' => 9502,

        /**
         * Metrics server port.
         *
         * @var int
         */
        'metrics_port' => 9310,

        /**
         * Swoole server settings.
         *
         * @var array
         */
        'settings' => [
            /**
             * Number of worker processes.
             *
             * @var int
             */
            'worker_num' => (int)(env('SWOOLE_WORKER_NUM', swoole_cpu_num())),

            /**
             * Number of task worker processes.
             *
             * @var int
             */
            'task_worker_num' => (int)(env('SWOOLE_TASK_WORKER_NUM', max(2, (int)(swoole_cpu_num() / 2)))),

            /**
             * Enable coroutine in task workers.
             *
             * @var bool
             */
            'task_enable_coroutine' => true,

            /**
             * Max requests per worker before reload.
             *
             * @var int
             */
            'max_request' => (int)(env('SWOOLE_MAX_REQUEST', 10000)),

            /**
             * Enable coroutine.
             *
             * @var bool
             */
            'enable_coroutine' => true,

            /**
             * Swoole hook flags.
             *
             * @var int
             */
            'hook_flags' => SWOOLE_HOOK_ALL,

            /**
             * Enable HTTP/2 protocol.
             *
             * @var bool
             */
            'open_http2_protocol' => true,

            /**
             * Enable HTTP compression.
             *
             * @var bool
             */
            'http_compression' => true,

            /**
             * Ssl alpn protocols.
             *
             * @var string
             */
            'ssl_alpn_protocols' => 'h2,http/1.1',  // critical for HTTP/2

            /**
             * Enable HTTP POST parsing.
             *
             * @var bool
             */
            'http_parse_post' => true,

            /**
             * Run as daemon.
             *
             * @var bool
             */
            'daemonize' => false,

            /**
             * TCP backlog for pending connections.
             *
             * @var int
             */
            'backlog' => 1024,

            /**
             * Enable async reload for zero downtime.
             *
             * @var bool
             */
            'reload_async' => true,
            // 'log_level' => SWOOLE_LOG_ERROR,
            'log_file' => '/app/logs/swoole.log',
            // 'log_file' => '/dev/stdout',
        ],

        /**
         * SSL configuration.
         *
         * @var array
         */
        'ssl' => [
            /**
             * Enable SSL.
             *
             * @var bool
             */
            'enable' => (env('SSL_ENABLE', 'false') === 'true'),

            /**
             * Path to SSL certificate file.
             *
             * @var string
             */
            'cert_file' => '/etc/ssl/certs/server.crt',

            /**
             * Path to SSL key file.
             *
             * @var string
             */
            'key_file' => '/etc/ssl/private/server.key',
        ],
    ],

    /**
     * Database configuration.
     *
     * @var array
     */
    'db' => [
        /**
         * Database driver (mysql, sqlite, etc).
         *
         * @var string
         */
        'driver' => env('DB_DRIVER', 'mysql'),

        /**
         * MySQL configuration.
         *
         * @var array
         */
        'mysql' => [
            'host'    => env('DB_HOST', 'mysql'),
            'port'    => 3306,
            'user'    => env('DB_USER', 'app'),
            'pass'    => env('DB_PASS', 'app'),
            'db'      => env('DB_DATABASE', 'app'),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'timeout' => (int)(env('DB_TIMEOUT', 2)),
            'pool'    => [
                'min' => (int)(env('DB_POOL_MIN', 5)),
                'max' => (int)(env('DB_POOL_MAX', 200)),
            ],
        ],

        /**
         * PDO configuration.
         *
         * @var array
         */
        'pdo' => [
            'host'    => env('DB_HOST', 'mysql'),
            'dsn'     => env('DB_DSN', 'mysql:host=mysql;dbname=app;charset=utf8mb4'),
            'user'    => env('DB_USER', 'app'),
            'pass'    => env('DB_PASS', 'app'),
            'db'      => env('DB_DATABASE', 'app'),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'timeout' => (int)(env('DB_TIMEOUT', 2)),
            'options' => [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT               => false, // we manage pool manually
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_EMULATE_PREPARES         => true,
            ],
            'pool'    => [
                'min' => (int)(env('DB_POOL_MIN', 5)),
                'max' => (int)(env('DB_POOL_MAX', 200)),
            ],
        ],

        /**
         * SQLite configuration.
         *
         * @var array
         */
        'sqlite' => [
            'dsn'     => 'sqlite:database/testing.db',
            'user'    => null,
            'pass'    => null,
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'options' => [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT               => false, // we manage pool manually
            ],
            'pool'    => [
                'min' => (int)(env('DB_POOL_MIN', 5)),
                'max' => (int)(env('DB_POOL_MAX', 200)),
            ],
        ],
    ],

    /**
     * Redis configuration.
     *
     * @var array
     */
    'redis' => [
        'host'     => env('REDIS_HOST', 'redis'),
        'port'     => (int)(env('REDIS_PORT', 6379)),
        'password' => env('REDIS_PASSWORD'),
        'database' => (int) env('REDIS_DATABASE', 0),
        'pool'     => [
            'min' => (int)(env('REDIS_POOL_MIN', 5)),
            'max' => (int)(env('REDIS_POOL_MAX', 200)),
        ],
    ],

    /**
     * Queue configuration.
     *
     * @var array
     */
    'queue' => [
        /**
         * Supported queue priorities.
         *
         * @var array
         */
        'priorities' => ['high', 'default', 'low'],

        /**
         * Maximum number of pending jobs before backpressure.
         *
         * @var int
         */
        'backpressure_max_pending' => (int)(env('QUEUE_MAX_PENDING', 10000)),
    ],

    'cors' => [
        'origin' => env('CORS_ORIGIN', '*'),
    ],

    'rateLimit' => [
        'throttle' => env('RATE_LIMIT_THROTTLE', 100),
        'skip_ip_patterns' => env('RATE_LIMIT_SKIP_IP_PATTERN', '/^(127\.0\.0\.1|::1|172\.\d{1,3}\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3})$/'),
    ]
];
