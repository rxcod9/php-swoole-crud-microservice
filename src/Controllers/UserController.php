<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Messages;
use App\Services\Cache\CacheService;
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
        private CacheService $cache
    ) {
        //
    }

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
            new OA\Response(response: 400, description: 'Invalid input'),
        ]
    )]
    public function create(): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $data = $this->svc->create($data);

        // Invalidate cache
        $this->cache->invalidateLists('users');

        return $this->json($data, 201);
    }

    /**
     * List users with optional pagination.
     * Query params: limit (default 100), offset (default 0)
     */
    #[OA\Get(
        path: '/users',
        summary: 'List users',
        description: 'Get all users with optional pagination. Use either page & limit or offset & limit',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'email',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: null)
            ),
            new OA\Parameter(
                name: 'name',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: null)
            ),
            new OA\Parameter(
                name: 'created_after',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'date', default: null)
            ),
            new OA\Parameter(
                name: 'created_before',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'date', default: null)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: null, maximum: 1000)
            ),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: null, minimum: 0)
            ),
            new OA\Parameter(
                name: 'sortBy',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['id', 'email', 'created_at', 'updated_at'], // allowed columns
                    default: 'id'
                )
            ),
            new OA\Parameter(
                name: 'sortDirection',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['ASC', 'DESC'], // allowed columns
                    default: 'DESC'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3001),
                                    new OA\Property(property: 'name', type: 'string', example: 'string'),
                                    new OA\Property(property: 'email', type: 'string', example: 'string'),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-09-21 09:28:37'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2025-09-21 09:28:37'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 1),
                                new OA\Property(property: 'count', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 100),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'total_pages', type: 'integer', example: 1),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(): array
    {
        // Pagination params
        $page   = (int)($this->request->get['page'] ?? 1);
        $limit  = max(1, min((int)($this->request->get['limit'] ?? 20), 100));
        $offset = max(0, ($page - 1) * $limit);

        // Override with direct offset if provided
        $limit  = (int)($this->request->get['limit'] ?? $limit);
        $offset = (int)($this->request->get['offset'] ?? $offset);

        $filters = [
            'email'          => $this->request->get['email'] ?? null,
            'name'           => $this->request->get['name'] ?? null,
            'created_after'  => $this->request->get['created_after'] ?? null,
            'created_before' => $this->request->get['created_before'] ?? null,
        ];

        $sortBy        = $this->request->get['sortBy'] ?? 'id';
        $sortDirection = $this->request->get['sortDirection'] ?? 'DESC';

        // Cache key based on pagination params
        $query = [
            'limit'         => $limit,
            'offset'        => $offset,
            'filters'       => array_filter($filters),
            'sortBy'        => $sortBy,
            'sortDirection' => $sortDirection,
        ];
        [$cacheTag, $cached] = $this->cache->getList('users', $query);
        if ($cached) {
            return $this->json(
                data: $cached,
                cacheTag: $cacheTag
            );
        }

        // Fetch from service if not cached
        [$records, $pagination] = $this->svc->pagination(
            $limit,
            $offset,
            $filters,
            $sortBy,
            $sortDirection
        );

        $data = [
            'data'       => $records,
            'pagination' => $pagination,
        ];

        // cache for 10s
        $this->cache->setList('users', $query, $data);

        // Respond with user list
        return $this->json($data);
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
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: Messages::ERROR_NOT_FOUND),
        ]
    )]
    public function show(array $params): array
    {
        $id = (int)$params['id'];

        [$cacheTag, $cached] = $this->cache->getRecord('users', $id);
        if ($cached) {
            return $this->json(
                data: $cached,
                cacheTag: $cacheTag
            );
        }

        $data = $this->svc->find($id);
        if (!$data) {
            return $this->json(['error' => Messages::ERROR_NOT_FOUND], 404);
        }

        $this->cache->setRecord('users', $id, $data);

        return $this->json($data);
    }

    /**
     * Show a single user by Email.
     * URL param: id
     */
    #[OA\Get(
        path: '/users/email/{email}',
        summary: 'Get user by Email',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'email',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: Messages::ERROR_NOT_FOUND),
        ]
    )]
    public function showByEmail(array $params): array
    {
        $email = (string)$params['email'];

        [$cacheTag, $cached] = $this->cache->getRecordByColumn('users', 'email', $email);
        if ($cached) {
            return $this->json(
                data: $cached,
                cacheTag: $cacheTag
            );
        }

        $data = $this->svc->findByEmail(urldecode($email));
        if (!$data) {
            return $this->json(['error' => Messages::ERROR_NOT_FOUND], 404);
        }

        $this->cache->setRecord('users', $data['id'], $data);
        $this->cache->setRecordByColumn('users', 'email', $email, $data);
        return $this->json($data);
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
            ),
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
            new OA\Response(response: 404, description: Messages::ERROR_NOT_FOUND),
        ]
    )]
    public function update(array $p): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $data = $this->svc->update((int)$p['id'], $data);
        if (!$data) {
            return $this->json(['error' => Messages::ERROR_NOT_FOUND], 404);
        }

        $this->cache->invalidateRecord('users', $data['id']);
        $this->cache->invalidateRecordByColumn('users', 'email', $data['email']);

        // Invalidate cache
        $this->cache->invalidateLists('users');
        return $this->json($data);
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
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'User deleted'),
            new OA\Response(response: 404, description: Messages::ERROR_NOT_FOUND),
        ]
    )]
    public function destroy(array $p): array
    {
        $ok = $this->svc->delete((int)$p['id']);
        if ($ok) {
            // Invalidate cache
            $this->cache->invalidateRecord('users', $p['id']);

            // Invalidate cache
            $this->cache->invalidateLists('users');
        }
        return $this->json(['deleted' => $ok], $ok ? 204 : 404);
    }
}
