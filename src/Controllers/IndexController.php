<?php

namespace App\Controllers;

use OpenApi\Attributes as OA;
use App\Core\Controller;

#[OA\Info(
    version: "1.0.0",
    title: "PHP Swoole CRUD Microservice API",
    description: "OpenAPI docs for PHP Swoole CRUD Microservice"
)]
#[OA\Server(
    url: "http://localhost:9501",
    description: "Local dev server"
)]

final class IndexController extends Controller
{
    public function __construct()
    {
        //
    }

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
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function index(): array
    {
        return $this->json(['message' => 'Welcome to PHP Swoole CRUD Microservice']);
    }
}
