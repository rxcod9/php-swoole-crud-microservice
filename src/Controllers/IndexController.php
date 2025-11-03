<?php

/**
 * src/Controllers/IndexController.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Controllers/IndexController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use OpenApi\Attributes as OA;

/**
 * Class IndexController
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
#[OA\Info(
    version: '1.0.0',
    title: 'PHP Swoole CRUD Microservice API',
    description: 'OpenAPI docs for PHP Swoole CRUD Microservice'
)]
#[OA\Server(
    url: 'http://localhost:9501',
    description: 'Local dev server'
)]
#[OA\Tag(
    name: 'Home',
    description: 'Root endpoints providing API metadata, health status, and basic navigation.'
)]
#[OA\Tag(
    name: 'Users',
    description: 'Endpoints for managing user entities — create, read, update, and delete operations.'
)]
#[OA\Tag(
    name: 'AsyncUsers',
    description: 'Asynchronous user operations handled via Swoole tasks or background workers.'
)]
#[OA\Tag(
    name: 'Items',
    description: 'Endpoints for managing item inventory, pricing, and stock lifecycle.'
)]
#[OA\Schema(
    x: [
        'tagGroups' => [
            [
                'name' => 'Home',
                'tags' => ['Home'],
            ],
            [
                'name' => 'Users',
                'tags' => ['Users', 'AsyncUsers'],
            ],
            [
                'name' => 'Items',
                'tags' => ['Items'],
            ],
        ],
    ]
)]

final class IndexController extends Controller
{
    public function __construct()
    {
        //
    }

    /**
     * Home endpoint.
     *
     * @return array<string,mixed> JSON response
     */
    #[OA\Get(
        path: '/',
        summary: 'Home',
        description: 'Home',
        tags: ['Home'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index(): array
    {
        return $this->json(['message' => 'Welcome to PHP Swoole CRUD Microservice']);
    }
}
