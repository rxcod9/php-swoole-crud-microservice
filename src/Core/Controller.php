<?php

/**
 * src/Core/Controller.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Controller.php
 */
declare(strict_types=1);

namespace App\Core;

/**
 * Class Controller
 * Abstract base controller providing common functionality for all controllers.
 * Handles request assignment and standardized JSON responses.
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
abstract class Controller
{
    /**
     * The container object associated with the controller.
     */
    protected mixed $container;

    /**
     * Assigns the container object to the controller.
     *
     * @param mixed $app The container object.
     */
    public function setContainer(mixed $app): void
    {
        $this->container = $app;
    }

    /**
     * The request object associated with the controller.
     */
    protected mixed $request;

    /**
     * Assigns the request object to the controller.
     *
     * @param mixed $req The request object.
     */
    public function setRequest(mixed $req): void
    {
        $this->request = $req;
    }

    /**
     * Returns a structured JSON response.
     *
     * @param mixed  $data        The data to encode as JSON.
     * @param int    $status      The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: application/json)
     *
     * @return array The structured response containing status and JSON data.
     */
    protected function json(
        mixed $data,
        int $status = 200,
        string $contentType = 'application/json',
        ?string $cacheTagType = null
    ): array {
        return [
            '__status'       => $status,
            '__json'         => $data,
            '__contentType'  => $contentType,
            '__cacheTagType' => $cacheTagType,
        ];
    }

    /**
     * Returns a structured HTML response.
     *
     * @param mixed  $data        The data as HTML.
     * @param int    $status      The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: text/html)
     *
     * @return array The structured response containing status and HTML data.
     */
    protected function html(
        mixed $data,
        int $status = 200,
        string $contentType = 'text/html',
        ?string $cacheTagType = null
    ): array {
        return [
            '__status'       => $status,
            '__html'         => $data,
            '__contentType'  => $contentType,
            '__cacheTagType' => $cacheTagType,
        ];
    }

    /**
     * Returns a structured Text response.
     *
     * @param mixed  $data        The data as Text.
     * @param int    $status      The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: text/plain)
     *
     * @return array The structured response containing status and Text data.
     */
    protected function text(
        mixed $data,
        int $status = 200,
        string $contentType = 'text/plain',
        ?string $cacheTagType = null
    ): array {
        return [
            '__status'       => $status,
            '__text'         => $data,
            '__contentType'  => $contentType,
            '__cacheTagType' => $cacheTagType,
        ];
    }
}
