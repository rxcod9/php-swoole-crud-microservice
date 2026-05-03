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

use App\Core\Http\Request;
use App\Core\Http\Response;

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
    protected Container $container;

    /**
     * Assigns the container object to the controller.
     *
     * @param Container $container The container object.
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * The request object associated with the controller.
     */
    protected Request $request;

    /**
     * Assigns the request object to the controller.
     *
     * @param Request $request The request object.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * The response object associated with the controller.
     */
    protected Response $response;

    /**
     * Assigns the response object to the controller.
     *
     * @param Response $response The response object.
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    /**
     * Returns a structured JSON response.
     *
     * @param mixed  $data        The data to encode as JSON.
     * @param int    $status      The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: application/json)
     *
     * @return Response The structured response object.
     */
    protected function json(
        mixed $data,
        int $status = 200,
        string $contentType = 'application/json',
        ?string $cacheTagType = null
    ): Response {
        $this->response->setStatus($status);
        if ($cacheTagType !== null) {
            $this->response->setHeader('X-Cache-Type', $cacheTagType);
        }

        $this->response->setHeader('Content-Type', $contentType);
        $this->response->setBody($status === 204 ? '' : $data);
        return $this->response;
    }

    /**
     * Returns a structured HTML response.
     *
     * @param mixed  $data        The data as HTML.
     * @param int    $status      The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: text/html)
     *
     * @return Response The structured response object.
     */
    protected function html(
        mixed $data,
        int $status = 200,
        string $contentType = 'text/html',
        ?string $cacheTagType = null
    ): Response {
        $this->response->setStatus($status);
        if ($cacheTagType !== null) {
            $this->response->setHeader('X-Cache-Type', $cacheTagType);
        }

        $this->response->setHeader('Content-Type', $contentType);
        $this->response->setBody($status === 204 ? '' : $data);
        return $this->response;
    }

    /**
     * Returns a structured Text response.
     *
     * @param mixed  $data        The data as Text.
     * @param int    $status      The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: text/plain)
     *
     * @return Response The structured response object.
     */
    protected function text(
        mixed $data,
        int $status = 200,
        string $contentType = 'text/plain',
        ?string $cacheTagType = null
    ): Response {
        $this->response->setStatus($status);
        if ($cacheTagType !== null) {
            $this->response->setHeader('X-Cache-Type', $cacheTagType);
        }

        $this->response->setHeader('Content-Type', $contentType);
        $this->response->setBody($status === 204 ? '' : $data);
        return $this->response;
    }
}
