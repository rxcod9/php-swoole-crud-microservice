<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RedisContext;
use App\Services\UserService;
use OpenApi\Attributes as OA;

/**
 * RESTful User resource controller
 * Handles CRUD operations for User entities.
 */
final class UserController extends Controller
{
    /**
     * Inject UserService for business logic operations.
     */
    public function __construct(
        private UserService $svc,
        private RedisContext $ctx
    ) {}

    /**
     * Create a new user.
     * Expects JSON body with user data.
     */
    #[OA\Post(
        path: '/users',
        summary: 'Create user',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created'),
            new OA\Response(response: 400, description: 'Invalid input')
        ]
    )]
    public function create(): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $user = $this->svc->create($data);

        $redis = $this->ctx->conn(); // returns Swoole\Coroutine\Redis
        // Invalidate cache
        $redis->del("users:list:100:0"); // Invalidate all list caches
        $redis->del("users:list:100:100"); // Invalidate all list caches
        $redis->del("users:list:100:200"); // Invalidate all list caches

        return $this->json($user, 201);
    }

    /**
     * List users with optional pagination.
     * Query params: limit (default 100), offset (default 0)
     */
    #[OA\Get(
        path: '/users',
        summary: 'List users',
        description: 'Get all users with optional pagination',
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string'),
                        ]
                    )
                )
            )
        ]
    )]
    public function index(): array
    {
        // Simple caching with Redis
        $redis = $this->ctx->conn(); // returns Swoole\Coroutine\Redis
        $limit = (int)($this->request->get['limit'] ?? 100);
        $offset = (int)($this->request->get['offset'] ?? 0);

        // Cache key based on pagination params
        $cacheKey = "users:list:$limit:$offset";
        if ($cached = $redis->get($cacheKey)) {
            return $this->json(json_decode($cached));
        }

        // Fetch from service if not cached
        $users = $this->svc->list($limit, $offset);

        // cache for 60s
        $redis->setex($cacheKey, 10, json_encode($users));

        // Respond with user list
        return $this->json($users);
    }

    /**
     * Show a single user by ID.
     * URL param: id
     */
    #[OA\Get(
        path: '/users/{id}',
        summary: 'Get user by ID',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    public function show(array $params): array
    {
        $redis = $this->ctx->conn(); // returns Swoole\Coroutine\Redis
        $id = (int)$params['id'];
        $cacheKey = "user:$id";

        if ($cached = $redis->get($cacheKey)) {
            return $this->json(json_decode($cached));
        }

        $u = $this->svc->get($id);
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }

        $redis->setex($cacheKey, 300, json_encode($u)); // 5 min cache
        return $this->json($u);
    }

    /**
     * Update a user by ID.
     * URL param: id
     * Expects JSON body with updated user data.
     */
    #[OA\Put(
        path: '/users/{id}',
        summary: 'Update user',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User updated'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    public function update(array $p): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $u = $this->svc->update((int)$p['id'], $data);
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }

        $redis = $this->ctx->conn(); // returns Swoole\Coroutine\Redis
        // Invalidate caches
        $redis->del("user:{$p['id']}");
        $redis->del("users:list:100:0"); // Invalidate all list caches
        $redis->del("users:list:100:100"); // Invalidate all list caches
        $redis->del("users:list:100:200"); // Invalidate all list caches
        return $this->json($u);
    }

    /**
     * Delete a user by ID.
     * URL param: id
     */
    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Delete user',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    public function destroy(array $p): array
    {
        $ok = $this->svc->delete((int)$p['id']);
        if ($ok) {
            $redis = $this->ctx->conn(); // returns Swoole\Coroutine\Redis
            // Invalidate cache
            $redis->del("user:{$p['id']}");
            $redis->del("users:list:100:0"); // Invalidate all list caches
            $redis->del("users:list:100:100"); // Invalidate all list caches
            $redis->del("users:list:100:200"); // Invalidate all list caches
        }
        return $this->json(['deleted' => $ok], $ok ? 204 : 404);
    }
}
