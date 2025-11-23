<?php

/**
 * src/Core/Http/Response.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Http
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Http/Response.php
 */
declare(strict_types=1);

namespace App\Core\Http;

use Swoole\Http\Response as HttpResponse;

/**
 * Class Response
 * Wraps Swoole\Http\Response and allows delayed mutation of
 * headers, status codes, and body content. Ideal for middleware chaining.
 *
 * @category  Core
 * @package   App\Core\Http
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
class Response
{
    /**
     * HTTP status code.
     */
    private int $statusCode = 200;

    /**
     * HTTP response headers.
     *
     * @var array<string, string|null>
     */
    private array $headers = [];

    /**
     * Response body (string|array|object|null).
     */
    private mixed $body = null;

    /**
     * Response constructor.
     *
     * @param HttpResponse $httpResponse Swoole response object.
     */
    public function __construct(private readonly HttpResponse $httpResponse)
    {
        //
    }

    /**
     * Set HTTP status code.
     */
    public function setStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Retrieve current status code.
     */
    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /**
     * Add or replace a header.
     */
    public function setHeader(string $key, ?string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Retrieve all headers.
     *
     * @return array<string, string|null>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Retrieve header.
     */
    public function getHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    /**
     * Set response body.
     */
    public function setBody(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Retrieve body before it’s sent.
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Finalize and send response to client.
     *
     * Automatically sets JSON headers when array/object body is detected.
     */
    public function send(): void
    {
        $this->httpResponse->status($this->statusCode);

        foreach ($this->headers as $key => $value) {
            $this->httpResponse->header($key, $value);
        }

        if (is_array($this->body) || is_object($this->body)) {
            $this->httpResponse->header('Content-Type', 'application/json');
            $this->httpResponse->end(json_encode($this->body, JSON_UNESCAPED_UNICODE));
            return;
        }

        $this->httpResponse->end((string) $this->body);
    }
}
