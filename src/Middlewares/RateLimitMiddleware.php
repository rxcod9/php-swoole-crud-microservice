<?php

/**
 * src/Middlewares/RateLimitMiddleware.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/RateLimitMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Config;
use App\Core\Container;
use App\Services\Cache\CacheService;
use Carbon\Carbon;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class RateLimitMiddleware
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public $table;

    // exclude paths
    private array $excludePaths = [
        '/health',
        '/health.html',
    ];

    private array $options = [
        'windowSec' => 60, // 60 seconds
    ];

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly Config $config
    ) {
        //
    }

    public function handle(Request $request, Response $response, Container $container, callable $next): void
    {
        $rateLimitConfig = $this->config->get('rateLimit') ?? [];
        // Allow public paths without auth
        if (in_array($request->server['request_uri'], $this->excludePaths, true)) {
            $next($request, $response);
            return;
        }

        $ip    = $request->server['remote_addr'] ?? 'unknown';
        $limit = (int) ($rateLimitConfig['throttle'] ?? 100); // requests per minute

        $clientIp       = $request->server['remote_addr'] ?? '';
        $skipIpPatterns = $rateLimitConfig['skip_ip_patterns'] ?? null; // '/^172\.17\.\d+\.\d+$/'; // matches default Docker bridge

        if ($skipIpPatterns && preg_match($skipIpPatterns, $clientIp)) {
            $next($request, $response);
            return;
        }

        error_log('IP: ' . $ip);

        [$row]     = $this->cacheService->getRecordByColumn('rateLimit', 'ip', $ip) ?? [];
        $nowSec    = Carbon::now()->getTimestamp();
        $count     = 0;
        $oldest    = $nowSec;
        $expiresAt = $nowSec + 60;
        $elapsed   = $nowSec - $oldest;

        if ($row === null) {
            $this->cacheService->setRecordByColumn(
                'rateLimit',
                'ip',
                $ip,
                ['value' => 1, 'created_at' => $nowSec, 'expires_at' => $expiresAt],
                60
            );
            $count = 1;
        } else {
            $oldest    = (int)($row['created_at'] ?? 0);
            $expiresAt = (int)($row['expires_at'] ?? $expiresAt);
            $count     = (int)($row['value'] ?? 0);
            $elapsed   = $nowSec - $oldest;
            if ($elapsed < ($this->options['windowSec'])) {
                $this->cacheService->setRecordByColumn(
                    'rateLimit',
                    'ip',
                    $ip,
                    ['value' => $count + 1, 'created_at' => $oldest, 'expires_at' => $expiresAt],
                    60
                );
            } else {
                $oldest    = $nowSec;
                $expiresAt = $nowSec + 60;
                $elapsed   = $nowSec - $oldest;
                $this->cacheService->setRecordByColumn(
                    'rateLimit',
                    'ip',
                    $ip,
                    ['value' => 1, 'created_at' => $nowSec, 'expires_at' => $expiresAt],
                    60
                );
                $count = 1;
            }
        }

        if ($count >= $limit) {
            $retryAfter = (int) ceil($this->options['windowSec'] - $elapsed);
            $resetAt    = $retryAfter;

            $response->status(429);
            $response->header('Retry-After', (string) $retryAfter);
            $response->header('X-RateLimit-Limit', (string) $limit);
            $response->header('X-RateLimit-Remaining', '0');
            $response->header('X-RateLimit-Reset', (string) $resetAt);

            $response->header('Content-Type', 'application/json');
            $response->end(json_encode(['error' => 'Too many requests']));
            return;
        }

        // Normal response: set headers
        $remaining  = max(0, $limit - $count);
        $retryAfter = (int) ceil($this->options['windowSec'] - $elapsed);
        $resetAt    = $retryAfter;

        $response->header('X-RateLimit-Limit', (string) $limit);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        $response->header('X-RateLimit-Reset', (string) $resetAt);

        $next($request, $response);
    }
}
