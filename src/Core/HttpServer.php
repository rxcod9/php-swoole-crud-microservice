<?php

namespace App\Core;

use Swoole\Http\Server as SwooleHttp;
use Swoole\Http\Request;
use Swoole\Http\Response;
use App\Middlewares\AuthMiddleware;
use App\Tasks\LoggerTask;

final class HttpServer
{
  private SwooleHttp $http;
  private Router $router;
  private array $config;
  private MySQLPool $mysql;
  private RedisPool $redis;

  public function __construct(array $config, Router $router)
  {
    $this->config = $config;
    $this->router = $router;

    $host = $config['server']['host'];
    $port = $config['server']['http_port'];

    $this->http = new SwooleHttp($host, $port);
    $this->http->set($config['server']['settings'] ?? []);

    // optional TLS/HTTP2
    if (($config['server']['ssl']['enable'] ?? false) === true) {
      $this->http->set([
        'ssl_cert_file' => $config['server']['ssl']['cert_file'],
        'ssl_key_file' => $config['server']['ssl']['key_file'],
      ]);
    }

    $this->http->on('start', fn() => print("HTTP listening on {$host}:{$port}\n"));

    $this->http->on('workerStart', function () use ($config) {
      // Initialize pools per worker
      $dbConf = $config['db']['mysql'];
      $this->mysql = new MySQLPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);
      $this->redis = new RedisPool($config['redis'], $config['redis']['pool']['min'], $config['redis']['pool']['max'] ?? 200);
      AppContext::setWorkerReady(true);
    });

    $this->http->on('request', function (Request $req, Response $res) {
      // ini_set('display_errors', 1);
      // ini_set('display_startup_errors', 1);
      // error_reporting(E_ALL);

      if (!AppContext::isWorkerReady()) {
        $res->status(503);
        $res->end("Service Unavailable – worker not ready");
        return;
      }

      $container = new Container();
      $reqId = bin2hex(random_bytes(8));
      $start = microtime(true);

      try {
        // Auth
        (new AuthMiddleware())->handle($req, $container);

        // Bind request-scoped DbContext (one MySQL and redis connection per request)
        if (!isset($this->mysql) || !isset($this->redis)) {
          throw new \RuntimeException('Database pools not initialized after waiting');
        }

        $conn = $this->mysql->get();
        $redisConn = $this->redis->get();
        $container->bind(DbContext::class, fn() => new DbContext($conn));
        $container->bind(MySQLPool::class, fn() => $this->mysql);

        $container->bind(RedisContext::class, fn() => new RedisContext($redisConn));
        $container->bind(RedisPool::class, fn() => $this->redis);

        // Metrics
        $reg = Metrics::reg();
        $counter = $reg->getOrRegisterCounter(
          'http_requests_total',
          'Requests',
          'Total HTTP requests',
          ['method', 'path', 'status']
        );
        $hist = $reg->getOrRegisterHistogram(
          'http_request_seconds',
          'Latency',
          'HTTP request latency',
          ['method', 'path']
        );

        // Routing
        // try {
        [$action, $params] = $this->router->match($req->server['request_method'], $req->server['request_uri']);
        $payload = (new Dispatcher($container))->dispatch($action, $params, $req);

        $status = $payload['__status'] ?? 200;
        $json = $payload['__json'] ?? $payload;

        $res->header('Content-Type', 'application/json');
        $res->status($status);
        $res->end(json_encode($json));
        // } catch (\Throwable $e) {
        //   $status = $e->getCode() ?: 500;
        //   $res->header('Content-Type', 'application/json');
        //   $res->status($status);
        //   $res->end(json_encode(['error' => $e->getMessage()]));
        // }

        // Metrics + async logging
        $dur = microtime(true) - $start;
        $path = parse_url($req->server['request_uri'] ?? '/', PHP_URL_PATH);

        $counter->inc([$req->server['request_method'], $path, (string)$status]);
        $hist->observe($dur, [$req->server['request_method'], $path]);

        $this->http->task([
          'type' => 'log',
          'data' => [
            'id' => $reqId,
            'method' => $req->server['request_method'],
            'path' => $path,
            'status' => $status,
            'dur_ms' => (int)round($dur * 1000)
          ]
        ]);
      } catch (\Throwable $e) {
        $status = $e->getCode() ?: 500;
        $res->header('Content-Type', 'application/json');
        $res->status($status);
        $res->end(json_encode(['error' => $e->getMessage()]));
      } finally {
        // Release MySQL connection back to pool
        if (isset($conn)) {
          $this->mysql->put($conn);
        }
        if (isset($redisConn)) {
          $this->redis->put($redisConn);
        }
      }
    });

    // Task handling for async jobs
    $this->http->on('task', function (SwooleHttp $server, \Swoole\Server\Task $task) {
      $data = $task->data;
      if (($data['type'] ?? '') === 'log') {
        LoggerTask::handle($data['data']);
      }
      return true;
    });

    $this->http->on('finish', fn() => null);
  }

  public function start(): void
  {
    $this->http->start();
  }
}
