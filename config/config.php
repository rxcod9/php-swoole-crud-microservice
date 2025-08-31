<?php
return [
  'app' => [
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => (bool)(getenv('APP_DEBUG') ?: false),
    'name' => 'php-swoole-crud-microservice',
    'timezone' => 'Asia/Kolkata',
  ],
  'server' => [
    'host' => '0.0.0.0',
    'http_port' => 9501,
    'ws_port' => 9502,
    'metrics_port' => 9310,
    'settings' => [
      'worker_num' => (int)(getenv('SWOOLE_WORKER_NUM') ?: swoole_cpu_num()),
      'task_worker_num' => (int)(getenv('SWOOLE_TASK_WORKER_NUM') ?: max(2, (int)(swoole_cpu_num()/2))),
      'task_enable_coroutine' => true,
      'max_request' => (int)(getenv('SWOOLE_MAX_REQUEST') ?: 10000),
      'enable_coroutine' => true,
      'hook_flags' => SWOOLE_HOOK_ALL,
      'open_http2_protocol' => true,
      'http_compression' => true,
      'http_parse_post' => true,
      'daemonize' => false,
      'backlog' => 1024,       // TCP backlog for pending connections
      'reload_async' => true,  // optional for zero downtime reloads
    ],
    'ssl' => [
      'enable' => (bool)(getenv('SSL_ENABLE') ?: false),
      'cert_file' => '/etc/ssl/certs/server.crt',
      'key_file' => '/etc/ssl/private/server.key',
    ]
  ],
  'db' => [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
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
    'pdo' => [
      'dsn'  => getenv('DB_DSN') ?: 'mysql:host=mysql;dbname=app;charset=utf8mb4',
      'user' => getenv('DB_USER') ?: 'app',
      'pass' => getenv('DB_PASS') ?: 'app',
      'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
      'pool' => [
        'min' => (int)(getenv('DB_POOL_MIN') ?: 5),
        'max' => (int)(getenv('DB_POOL_MAX') ?: 200),
      ],
    ],
    'sqlite' => [
      'dsn' => 'sqlite::memory:',
      'user' => null,
      'pass' => null,
      'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    ]
  ],
  'redis' => [
    'host' => getenv('REDIS_HOST') ?: 'redis',
    'port' => (int)(getenv('REDIS_PORT') ?: 6379),
    'pool' => [
      'min' => (int)(getenv('REDIS_POOL_MIN') ?: 5),
      'max' => (int)(getenv('REDIS_POOL_MAX') ?: 200),
    ]
  ],
  'queue' => [
    'priorities' => ['high','default','low'],
    'backpressure_max_pending' => (int)(getenv('QUEUE_MAX_PENDING') ?: 10000),
  ]
];
