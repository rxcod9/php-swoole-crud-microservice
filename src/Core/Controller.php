<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Class Controller
 *
 * Abstract base controller providing common functionality for all controllers.
 * Handles request assignment and standardized JSON responses.
 *
 * @package App\Core
 */
abstract class Controller
{
    /**
     * The request object associated with the controller.
     *
     * @var mixed
     */
    protected $request;

    /**
     * Assigns the request object to the controller.
     *
     * @param mixed $req The request object.
     */
    public function setRequest($req): void
    {
        $this->request = $req;
    }

    /**
     * Returns a structured JSON response.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $status The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: application/json)
     *
     * @return array The structured response containing status and JSON data.
     */
    protected function json(
        $data,
        int $status = 200,
        string $contentType = 'application/json'
    ): array {
        return [
            '__status'      => $status,
            '__json'        => $data,
            '__contentType' => $contentType,
        ];
    }

    /**
     * Returns a structured HTML response.
     *
     * @param mixed $data The data as HTML.
     * @param int $status The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: text/html)
     *
     * @return array The structured response containing status and HTML data.
     */
    protected function html(
        $data,
        int $status = 200,
        string $contentType = 'text/html'
    ): array {
        return [
            '__status'      => $status,
            '__html'        => $data,
            '__contentType' => $contentType,
        ];
    }

    /**
     * Returns a structured Text response.
     *
     * @param mixed $data The data as Text.
     * @param int $status The HTTP status code (default: 200).
     * @param string $contentType Content-Type (default: text/plain)
     *
     * @return array The structured response containing status and Text data.
     */
    protected function text(
        $data,
        int $status = 200,
        string $contentType = 'text/plain'
    ): array {
        return [
            '__status'      => $status,
            '__text'        => $data,
            '__contentType' => $contentType,
        ];
    }
}
