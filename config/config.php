<?php

/**
 * Application configuration file for php-swoole-crud-microservice.
 *
 * This file returns an array containing configuration settings for the application,
 * server, database, Redis, and queue. Environment variables are used where available,
 * with sensible defaults provided for local development.
 *
 * @package php-swoole-crud-microservice
 * @author  Your Name
 * @license MIT
 * @version 1.0.0
 * @see     https://github.com/your-repo/php-swoole-crud-microservice
 */

return [
  /**
   * Application settings.
   *
   * @var array
   */
  'app' => [
    /**
     * Application environment (e.g., local, production).
     *
     * @var string
     */
    'env' => getenv('APP_ENV') ?: 'local',

    /**
     * Enable debug mode.
     *
     * @var bool
     */
    'debug' => (bool)(getenv('APP_DEBUG') ?: false),

    /**
     * Application name.
     *
     * @var string
     */
    'name' => 'php-swoole-crud-microservice',

    /**
     * Default timezone.
     *
     * @var string
     */
    'timezone' => 'Asia/Kolkata',
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
      'worker_num' => (int)(getenv('SWOOLE_WORKER_NUM') ?: swoole_cpu_num()),

      /**
       * Number of task worker processes.
       *
       * @var int
       */
      'task_worker_num' => (int)(getenv('SWOOLE_TASK_WORKER_NUM') ?: max(2, (int)(swoole_cpu_num() / 2))),

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
      'max_request' => (int)(getenv('SWOOLE_MAX_REQUEST') ?: 10000),

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
      'log_file'  => '/app/logs/swoole.log',
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
      'enable' => (getenv('SSL_ENABLE') ?: "false") === "true",

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
    ]
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
    'driver' => getenv('DB_DRIVER') ?: 'mysql',

    /**
     * MySQL configuration.
     *
     * @var array
     */
    'mysql' => [
      'host' => 'mysql',
      'port' => 3306,
      'user' => getenv('DB_USER') ?: 'app',
      'pass' => getenv('DB_PASS') ?: 'app',
      'db'   => getenv('DB_DATABASE') ?: 'app',
      'charset'   => getenv('DB_CHARSET') ?: 'utf8mb4',
      'timeout'   => (int)(getenv('DB_TIMEOUT') ?: 2),
      'pool' => [
        'min' => (int)(getenv('DB_POOL_MIN') ?: 5),
        'max' => (int)(getenv('DB_POOL_MAX') ?: 200),
      ],
    ],

    /**
     * PDO configuration.
     *
     * @var array
     */
    'pdo' => [
      'dsn'  => getenv('DB_DSN') ?: 'mysql:host=mysql;dbname=app;charset=utf8mb4',
      'user' => getenv('DB_USER') ?: 'app',
      'pass' => getenv('DB_PASS') ?: 'app',
      'db'   => getenv('DB_DATABASE') ?: 'app',
      'charset'   => getenv('DB_CHARSET') ?: 'utf8mb4',
      'timeout'   => (int)(getenv('DB_TIMEOUT') ?: 2),
      'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
      'pool' => [
        'min' => (int)(getenv('DB_POOL_MIN') ?: 5),
        'max' => (int)(getenv('DB_POOL_MAX') ?: 200),
      ],
    ],

    /**
     * SQLite configuration.
     *
     * @var array
     */
    'sqlite' => [
      'dsn' => 'sqlite::memory:',
      'user' => null,
      'pass' => null,
      'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    ]
  ],

  /**
   * Redis configuration.
   *
   * @var array
   */
  'redis' => [
    'host' => getenv('REDIS_HOST') ?: 'redis',
    'port' => (int)(getenv('REDIS_PORT') ?: 6379),
    'pool' => [
      'min' => (int)(getenv('REDIS_POOL_MIN') ?: 5),
      'max' => (int)(getenv('REDIS_POOL_MAX') ?: 200),
    ]
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
    'backpressure_max_pending' => (int)(getenv('QUEUE_MAX_PENDING') ?: 10000),
  ]
];
