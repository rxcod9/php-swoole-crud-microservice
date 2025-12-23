<?php

/**
 * RateLimitMiddleware
 * -------------------
 * Applies request rate limiting per IP using cache-based tracking.
 * Implements a simple fixed-window algorithm with configurable limits.
 * Project: rxcod9/php-swoole-crud-microservice
 * PHP version: 8.5
 *
 * @category  Middleware
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-16
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/RateLimitMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Config;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\Cache\CacheRecordParams;
use App\Services\Cache\CacheService;
use Carbon\Carbon;

/**
 * Class RateLimitMiddleware
 * Middleware that applies rate limiting on incoming requests
 * based on client IP. It uses the CacheService to track
 * request counts within a configured time window.
 *
 * @category  Middleware
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-16
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public const TAG = 'RateLimitMiddleware';

    /**
     * List of paths that should bypass rate limiting.
     *
     * @var array<int, string>
     */
    private array $excludePaths = [
        '/health',
        '/health.html',
    ];

    /**
     * Rate limiting configuration defaults.
     *
     * @var array<string, int>
     */
    private array $options = [
        'windowSec' => 60, // Fixed 1-minute window
    ];

    /**
     * Constructor
     *
     * @param CacheService $cacheService Cache handler for storing rate-limit state
     * @param Config       $config       Config handler for retrieving runtime settings
     */
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly Config $config
    ) {
        // Empty constructor
    }

    /**
     * Handle the incoming HTTP request.
     *
     * Applies rate limiting per client IP, using cached counters.
     * If limit is reached, responds with 429 Too Many Requests.
     *
     * @param Request  $request  Incoming Swoole HTTP request
     * @param Response $response Outgoing Swoole HTTP response
     * @param callable $next     Next middleware in the pipeline
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        $rateLimitConfig = $this->config->get('rateLimit') ?? [];
        $ip              = $request->server['remote_addr'] ?? 'unknown';
        $limit           = (int)($rateLimitConfig['throttle'] ?? 100);
        $skipPattern     = $rateLimitConfig['skip_ip_patterns'] ?? null;

        // 🟢 Early exit: Allow excluded routes without limiting
        if ($this->isExcludedPath($request->getPath())) {
            $next($request, $response);
            return;
        }

        error_log('$request->server ' . print_r($request->server, true));
        // 🟢 Early exit: Allow IPs matching skip pattern (e.g., Docker bridge)
        if ($this->isSkippedIp($ip, $skipPattern)) {
            $next($request, $response);
            return;
        }

        // 🧠 Process the rate limit counter for the IP
        [$count, $limitReached, $retryAfter] = $this->processRateLimit($ip, $limit);

        // 🚫 Limit reached: respond with 429 Too Many Requests
        if ($limitReached) {
            $this->sendTooManyRequests($response, $limit, $retryAfter);
            return;
        }

        // ✅ Normal response: send informative rate-limit headers
        $remaining = max(0, $limit - $count);
        $response->setHeader('X-RateLimit-Limit', (string)$limit);
        $response->setHeader('X-RateLimit-Remaining', (string)$remaining);
        $response->setHeader('X-RateLimit-Reset', (string)$retryAfter);

        // Continue middleware pipeline
        $next($request, $response);
    }

    /**
     * Check whether a request path should bypass rate limiting.
     *
     * @param string $path Request URI path
     * @return bool True if excluded
     */
    private function isExcludedPath(string $path): bool
    {
        return in_array($path, $this->excludePaths, true);
    }

    /**
     * Check whether the given IP should be skipped from rate limiting.
     *
     * @param string      $ip      Client IP
     * @param string|null $pattern Optional regex pattern for skip IPs
     *
     * @return bool True if IP matches skip pattern
     */
    private function isSkippedIp(string $ip, ?string $pattern): bool
    {
        return $pattern !== null && preg_match($pattern, $ip);
    }

    /**
     * Process rate limit state for the given IP.
     *
     * Retrieves cached counter, updates it, and returns rate limit details.
     *
     * @param string $ip    Client IP
     * @param int    $limit Max allowed requests in current window
     *
     * @return array{int, bool, int} [$count, $limitReached, $retryAfter]
     */
    private function processRateLimit(string $ip, int $limit): array
    {
        $nowSec = Carbon::now()->getTimestamp();

        // 🔍 Get rate-limit record from cache
        [$row] = $this->cacheService->getRecordByColumn('rateLimit', 'ip', $ip);

        // 🆕 New IP — create record and allow
        if ($row === null) {
            $this->recordNewIp($ip, $nowSec);
            return [1, false, $this->options['windowSec']];
        }

        // 📈 Existing record: check time elapsed and increment counter
        $count      = (int)($row['value'] ?? 0);
        $oldest     = (int)($row['created_at'] ?? $nowSec);
        $elapsed    = $nowSec - $oldest;
        $retryAfter = (int)ceil($this->options['windowSec'] - $elapsed);

        // ⏰ Window expired — reset counter
        if ($elapsed >= $this->options['windowSec']) {
            $this->recordNewIp($ip, $nowSec);
            return [1, false, $retryAfter];
        }

        // 🔄 Update counter within the same window
        $this->updateRateLimit($ip, $count, $oldest);

        // Check if threshold reached
        $limitReached = $count >= $limit;

        return [$count, $limitReached, $retryAfter];
    }

    /**
     * Record a new IP entry in cache.
     *
     * @param string $ip      Client IP
     * @param int    $nowSec  Current timestamp (epoch seconds)
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function recordNewIp(string $ip, int $nowSec): void
    {
        $this->cacheService->setRecordByColumn(CacheRecordParams::fromArray([
            'entity' => 'rateLimit',
            'column' => 'ip',
            'value'  => $ip,
            'data'   => [
                'value'      => 1,
                'created_at' => $nowSec,
                'expires_at' => $nowSec + $this->options['windowSec'],
            ],
            'ttl' => $this->options['windowSec'],
        ]));
    }

    /**
     * Update existing rate limit counter for an IP.
     *
     * @param string $ip     Client IP
     * @param int    $count  Current request count
     * @param int    $oldest Start time of window (epoch seconds)
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function updateRateLimit(string $ip, int $count, int $oldest): void
    {
        $this->cacheService->setRecordByColumn(CacheRecordParams::fromArray([
            'entity' => 'rateLimit',
            'column' => 'ip',
            'value'  => $ip,
            'data'   => [
                'value'      => $count + 1,
                'created_at' => $oldest,
                'expires_at' => $oldest + $this->options['windowSec'],
            ],
            'ttl' => $this->options['windowSec'],
        ]));
    }

    /**
     * Send a 429 Too Many Requests response with headers.
     *
     * @param Response $response   Swoole HTTP Response object
     * @param int      $limit      Max requests per window
     * @param int      $retryAfter Time (seconds) before reset
     */
    private function sendTooManyRequests(Response $response, int $limit, int $retryAfter): void
    {
        $response->setStatus(429);
        $response->setHeader('Retry-After', (string)$retryAfter);
        $response->setHeader('X-RateLimit-Limit', (string)$limit);
        $response->setHeader('X-RateLimit-Remaining', '0');
        $response->setHeader('X-RateLimit-Reset', (string)$retryAfter);
        $response->setHeader('Content-Type', 'application/json');

        // End response with JSON body
        $response->setBody(json_encode(['error' => 'Too many requests']));
        $response->send();
    }
}
