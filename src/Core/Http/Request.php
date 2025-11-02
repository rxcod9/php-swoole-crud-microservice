<?php

/**
 * src/Core/Http/Request.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Http
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Http/Request.php
 */
declare(strict_types=1);

namespace App\Core\Http;

use BadMethodCallException;
use OutOfBoundsException;
use Swoole\Http\Request as HttpRequest;

/**
 * Class Request
 * Lightweight PSR-like wrapper around Swoole\Http\Request providing
 * convenient accessors for headers, params, JSON body, and authorization tokens.
 *
 * @category  Core
 * @package   App\Core\Http
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 * @method    string|false getContent()
 * @method    string|false rawContent()
 * @method    string|false getData()
 * @method    Request create(array<string, mixed> $options = [])
 * @method    int|false parse(string $data)
 * @method    bool isCompleted()
 * @method    string|false getMethod()
 */
class Request
{
    public int $fd = 0;

    public int $streamId = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $header;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $server;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $cookie;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $get;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $files;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $post;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $tmpfiles;

    /**
     * Normalized headers (lowercase keys for case-insensitive access).
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Request constructor.
     *
     * @param HttpRequest $httpRequest The Swoole HTTP request instance.
     */
    public function __construct(private readonly HttpRequest $httpRequest)
    {
        $this->fd       = $httpRequest->fd;
        $this->streamId = $httpRequest->streamId;
        $this->header   = $httpRequest->header;
        $this->server   = $httpRequest->server;
        $this->cookie   = $httpRequest->cookie;
        $this->get      = $httpRequest->get;
        $this->files    = $httpRequest->files;
        $this->post     = $httpRequest->post;
        $this->tmpfiles = $httpRequest->tmpfiles;

        $this->headers = $this->normalizeHeaders($this->httpRequest->header ?? []);
    }

    /**
     * Retrieve the HTTP method (e.g., GET, POST).
     */
    public function getMethod(): string
    {
        return strtoupper($this->httpRequest->server['request_method'] ?? 'GET');
    }

    /**
     * Retrieve the request URI.
     */
    public function getUri(): string
    {
        return $this->httpRequest->server['request_uri'] ?? '/';
    }

    /**
     * Retrieve the request URI path.
     */
    public function getPath(): string
    {
        $parsedPath = parse_url($this->getUri(), PHP_URL_PATH);
        return $parsedPath !== false ? $parsedPath : '/';
    }

    /**
     * Retrieve all headers in normalized form.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Retrieve a specific header by name.
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Get query parameter value (?param=value).
     *
     */
    public function get(string $key, mixed $default = null): int|string|null
    {
        return $this->httpRequest->get[$key] ?? $default;
    }

    /**
     * Get query parameters (?param=value).
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->httpRequest->get ?? [];
    }

    /**
     * Get POST parameters (form-encoded).
     *
     * @return array<string, mixed>
     */
    public function getPostParams(): array
    {
        return $this->httpRequest->post ?? $this->getJsonBody();
    }

    /**
     * Decode and return JSON request body as an associative array.
     *
     * @return array<string, mixed> Returns an empty array if body is empty or invalid JSON
     */
    public function getJsonBody(): array
    {
        // Get raw request content (always a string)
        $content = $this->httpRequest->rawContent();

        // Strict check for empty string
        if (in_array($content, [false, '', null], true)) {
            return [];
        }

        // Decode JSON as associative array
        $decoded = json_decode($content, true);

        // Ensure we return an array even if JSON is invalid
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Extract Bearer token from Authorization header.
     *
     * @example
     *   Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
     *
     * @return string|null The token string or null if absent.
     */
    public function getBearerToken(): ?string
    {
        $authHeader = $this->getHeader('authorization');
        if (in_array($authHeader, [null, '', '0'], true) || !str_starts_with(strtolower($authHeader), 'bearer ')) {
            return null;
        }

        return trim(substr($authHeader, 7));
    }

    /**
     * Normalize header names to lowercase for consistent access.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }

        return $normalized;
    }

    /**
     * Magic method to forward calls to the underlying Swoole request.
     *
     * @throws BadMethodCallException If method does not exist on the native request.
     */
    public function __call(mixed $name, mixed $arguments): mixed
    {
        if (!method_exists($this->httpRequest, (string) $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist in Request', (string) $name));
        }

        /** @phpstan-ignore-next-line - dynamic forward */
        return $this->httpRequest->{$name}(...$arguments);
    }

    /**
     * Proxy property reads to the underlying request.
     *
     * Supports common Swoole\Http\Request properties:
     * - get, post, cookie, files, header, server, rawContent
     *
     * @param string $name Property name to access
     *
     * @throws OutOfBoundsException If property not found.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'get'    => $this->httpRequest->get,
            'post'   => $this->httpRequest->post,
            'cookie' => $this->httpRequest->cookie,
            'files'  => $this->httpRequest->files,
            'header' => $this->httpRequest->header,
            'server' => $this->httpRequest->server,
            default  => throw new OutOfBoundsException(sprintf('Property "%s" not found on Request', $name))
        };
    }

    /**
     * Proxy property assignment to the underlying request.
     *
     * @throws OutOfBoundsException If property not found.
     */
    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'get'    => $this->httpRequest->get    = $value,
            'post'   => $this->httpRequest->post   = $value,
            'cookie' => $this->httpRequest->cookie = $value,
            'files'  => $this->httpRequest->files  = $value,
            'header' => $this->httpRequest->header = $value,
            'server' => $this->httpRequest->server = $value,
            default  => throw new OutOfBoundsException(sprintf('Property "%s" not found on Request', $name)),
        };
    }

    /**
     * Expose the native Swoole request object for advanced use-cases (DI-friendly).
     *
     * Controllers should prefer the wrapper for testability, but low-level handlers
     * can still obtain native request when needed.
     */
    public function getNativeRequest(): HttpRequest
    {
        return $this->httpRequest;
    }
}
