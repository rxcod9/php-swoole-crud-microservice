<?php

/**
 * src/Controllers/UserController.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Controllers/UserController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Messages;
use App\Services\Cache\CacheRecordParams;
use App\Services\Cache\CacheService;
use App\Services\UserService;
use OpenApi\Attributes as OA;

/**
 * RESTful User resource controller
 * Handles CRUD operations for User entities.
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class UserController extends Controller
{
    public const TAG = 'UserController';

    /**
     * Inject UserService for business logic operations.
     */
    public function __construct(
        private readonly UserService $userService,
        private readonly CacheService $cacheService
    ) {
        // Empty Constructor
    }

    /**
     * Create a new user.
     * Expects JSON body with user data.
     *
     * @return array<string, mixed> Created user data
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
        $data = json_decode($this->request->rawContent() ?? '[]', true);

        $user = $this->userService->create($data);

        // Invalidate cache
        $this->cacheService->invalidateLists('users');

        return $this->json($user, 201);
    }

    /**
     * List users with optional pagination.
     * Query params: limit (default 100), offset (default 0)
     *
     * @return array<string, mixed> List of users with pagination info
     */
    #[OA\Get(
        path: '/users',
        summary: 'List users',
        description: 'Get all users with optional pagination. Use either page & limit or offset & limit',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'email', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: null)),
            new OA\Parameter(name: 'name', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: null)),
            new OA\Parameter(name: 'created_after', in: 'query', required: false, schema: new OA\Schema(type: 'date', default: null)),
            new OA\Parameter(name: 'created_before', in: 'query', required: false, schema: new OA\Schema(type: 'date', default: null)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: null, maximum: 1000)),
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: null, minimum: 0)),
            new OA\Parameter(name: 'sortBy', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'id', enum: ['id', 'email', 'created_at', 'updated_at'])),
            new OA\Parameter(name: 'sortDirection', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'DESC', enum: ['ASC', 'DESC'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3001),
                                    new OA\Property(property: 'name', type: 'string', example: 'string'),
                                    new OA\Property(property: 'email', type: 'string', example: 'string'),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-09-21 09:28:37'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2025-09-21 09:28:37'),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 1),
                                new OA\Property(property: 'count', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 100),
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'total_pages', type: 'integer', example: 1),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index(): array
    {
        // --------------------
        // Resolve query params
        // --------------------
        [$limit, $offset]         = $this->resolvePagination();
        $filters                  = $this->resolveFilters();
        [$sortBy, $sortDirection] = $this->resolveSorting();

        // --------------------
        // Build query array
        // --------------------
        $query = [
            'limit'         => $limit,
            'offset'        => $offset,
            'filters'       => array_filter($filters, fn (?string $v): bool => $v !== null),
            'sortBy'        => $sortBy,
            'sortDirection' => $sortDirection,
        ];

        // --------------------
        // Check cache
        // --------------------
        $cachedResult = $this->getCachedUserList($query);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        // --------------------
        // Fetch from service + cache
        // --------------------
        return $this->fetchAndCacheUsers($query);
    }

    /**
     * Attempt to retrieve cached user list.
     * @param array<string, mixed> $query Contains limit, offset, filters, sortBy, sortDirection
     *
     * @return array<string, mixed>|null Cached user list or null if not found
     */
    private function getCachedUserList(array $query): ?array
    {
        [$users, $cacheTagType] = $this->cacheService->getList('users', $query);
        if ($users !== null) {
            return $this->json(data: $users, cacheTagType: $cacheTagType);
        }

        return null;
    }

    /**
     * Fetch users from service and cache results.
     * @param array<string, mixed> $query Contains limit, offset, filters, sortBy, sortDirection
     *
     * @return array<string, mixed> Fetched user list
     */
    private function fetchAndCacheUsers(array $query): array
    {
        [$records, $pagination] = $this->userService->pagination($query);

        $data = [
            'data'       => $records,
            'pagination' => $pagination,
        ];

        // cache for 10s
        $this->cacheService->setList('users', $query, $data);

        return $this->json($data);
    }

    /**
     * Private helper: resolve pagination params
     *
     * @return array{int, int} [limit, offset]
     */
    private function resolvePagination(): array
    {
        $page   = (int)($this->request->get['page'] ?? 1);
        $limit  = max(1, min((int)($this->request->get['limit'] ?? 20), 100));
        $offset = max(0, ($page - 1) * $limit);

        // Override offset & limit if explicitly set
        $limit  = (int)($this->request->get['limit'] ?? $limit);
        $offset = (int)($this->request->get['offset'] ?? $offset);

        return [$limit, $offset];
    }

    /**
     * Private helper: resolve filter params
     *
     * @return array<string, string|null> Associative array of filters
     */
    private function resolveFilters(): array
    {
        return [
            'email'          => $this->request->get['email'] ?? null,
            'name'           => $this->request->get['name'] ?? null,
            'created_after'  => $this->request->get['created_after'] ?? null,
            'created_before' => $this->request->get['created_before'] ?? null,
        ];
    }

    /**
     * Private helper: resolve sorting params
     *
     * @return array{string, string} [sortBy, sortDirection]
     */
    private function resolveSorting(): array
    {
        return [
            $this->request->get['sortBy'] ?? 'id',
            $this->request->get['sortDirection'] ?? 'DESC',
        ];
    }

    /**
     * Show a single user by ID.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> User data
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
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function show(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $id                    = (int)$params['id'];
        [$user, $cacheTagType] = $this->cacheService->getRecord('users', $id);
        if ($user !== null) {
            return $this->json(data: $user, cacheTagType: $cacheTagType);
        }

        $data = $this->userService->find($id);

        $this->cacheService->setRecord('users', $id, $data);

        return $this->json($data);
    }

    /**
     * Show a single user by Email.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> User data
     * @SuppressWarnings("PHPMD.StaticAccess")
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
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function showByEmail(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['email']);
        $email                 = (string)$params['email'];
        [$user, $cacheTagType] = $this->cacheService->getRecordByColumn('users', 'email', $email);
        if ($user !== null) {
            return $this->json(data: $user, cacheTagType: $cacheTagType);
        }

        $data = $this->userService->findByEmail(urldecode($email));

        $this->cacheService->setRecord('users', $data['id'], $data);
        $this->cacheService->setRecordByColumn(CacheRecordParams::fromArray([
            'entity' => 'users',
            'column' => 'email',
            'value'  => $email,
            'data'   => $data,
        ]));

        return $this->json($data);
    }

    /**
     * Update a user by ID.
     * Expects JSON body with updated user data.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Updated user data
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
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function update(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $id   = (int)$params['id'];
        $data = json_decode($this->request->rawContent() ?? '[]', true);

        [$user] = $this->cacheService->getRecord('users', $id);
        if ($user !== null) {
            // Calling find to validate if entiry exists
            $this->userService->find($id);
        }

        $user = $this->userService->update((int)$params['id'], $data);

        $this->cacheService->invalidateRecord('users', (int) $data['id']);
        $this->cacheService->invalidateRecordByColumn('users', 'email', (string) $data['email']);
        $this->cacheService->invalidateLists('users');

        return $this->json($user);
    }

    /**
     * Delete a user by ID.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Deletion status
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
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function destroy(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $id = (int)$params['id'];
        $ok = $this->userService->delete($id);
        if ($ok) {
            $this->cacheService->invalidateRecord('users', $id);
            $this->cacheService->invalidateLists('users');
        }

        return $this->json(['deleted' => $ok], $ok ? 204 : 404);
    }
}
