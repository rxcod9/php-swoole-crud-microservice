<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ItemService;
use OpenApi\Attributes as OA;

/**
 * RESTful Item resource controller
 * Handles CRUD operations for Item entities.
 */
final class ItemController extends Controller
{
    /**
     * Inject ItemService for business logic operations.
     */
    public function __construct(private ItemService $svc)
    {
        //
    }

    /**
     * Create a new item.
     * Expects JSON body with item data.
     */
    #[OA\Post(
        path: '/items',
        summary: 'Create item',
        tags: ['Items'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['sku', 'title'],
                properties: [
                    new OA\Property(property: 'sku', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Item created'),
            new OA\Response(response: 400, description: 'Invalid input')
        ]
    )]
    public function create(): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $item = $this->svc->create($data);
        return $this->json($item, 201);
    }

    /**
     * List items with optional pagination.
     * Query params: limit (default 100), offset (default 0)
     */
    #[OA\Get(
        path: '/items',
        summary: 'List items',
        description: 'Get all items with optional pagination',
        tags: ['Items'],
        parameters: [
            new OA\Parameter(
                name: 'sku',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', default: null)
            ),
            new OA\Parameter(
                name: 'title',
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
                    enum: ["id", "sku", "created_at", "updated_at"], // allowed columns
                    default: 'id'
                )
            ),
            new OA\Parameter(
                name: 'sortDirection',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ["ASC", "DESC"], // allowed columns
                    default: 'DESC'
                )
            )
        ],
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
                            new OA\Property(property: 'sku', type: 'string'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'price', type: 'number', format: 'float'),
                        ]
                    )
                )
            )
        ]
    )]
    public function index(): array
    {
        // Pagination params
        $page = (int)($this->request->get['page'] ?? 1);
        $limit = max(1, min((int)($this->request->get['limit'] ?? 100), 1000));
        $offset = max(0, ($page - 1) * $limit);

        // Override with direct offset if provided
        $limit = (int)($this->request->get['limit'] ?? $limit);
        $offset = (int)($this->request->get['offset'] ?? $offset);
        $filters = [
            'sku' => $this->request->get['sku'] ?? null,
            'title' => $this->request->get['title'] ?? null,
            'created_after' => $this->request->get['created_after'] ?? null,
            'created_before' => $this->request->get['created_before'] ?? null,
        ];
        $sortBy = $this->request->get['sortBy'] ?? 'id';
        $sortDirection = $this->request->get['sortDirection'] ?? 'DESC';
        return $this->json($this->svc->list(
            $limit,
            $offset,
            $filters,
            $sortBy,
            $sortDirection
        ));
    }

    /**
     * Show a single item by ID.
     * URL param: id
     */
    #[OA\Get(
        path: '/items/{id}',
        summary: 'Get item by ID',
        tags: ['Items'],
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
                description: 'Item found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'sku', type: 'string'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'price', type: 'number', format: 'float'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Item not found')
        ]
    )]
    public function show(array $params): array
    {
        $u = $this->svc->find((int)$params['id']);
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }
        return $this->json($u);
    }

    /**
     * Show a single item by ID.
     * URL param: id
     */
    #[OA\Get(
        path: '/items/sku/{sku}',
        summary: 'Get item by SKU',
        tags: ['Items'],
        parameters: [
            new OA\Parameter(
                name: 'sku',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Item found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'sku', type: 'string'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'price', type: 'number', format: 'float'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Item not found')
        ]
    )]
    public function showBySku(array $params): array
    {
        $u = $this->svc->findBySku(urldecode((string)$params['sku']));
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }
        return $this->json($u);
    }

    /**
     * Update a item by ID.
     * URL param: id
     * Expects JSON body with updated item data.
     */
    #[OA\Put(
        path: '/items/{id}',
        summary: 'Update item',
        tags: ['Items'],
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
                    new OA\Property(property: 'sku', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Item updated'),
            new OA\Response(response: 404, description: 'Item not found')
        ]
    )]
    public function update(array $p): array
    {
        $data = json_decode($this->request->rawContent() ?: '[]', true);
        $u = $this->svc->update((int)$p['id'], $data);
        if (!$u) {
            return $this->json(['error' => 'Not Found'], 404);
        }
        return $this->json($u);
    }

    /**
     * Delete a item by ID.
     * URL param: id
     */
    #[OA\Delete(
        path: '/items/{id}',
        summary: 'Delete item',
        tags: ['Items'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Item deleted'),
            new OA\Response(response: 404, description: 'Item not found')
        ]
    )]
    public function destroy(array $p): array
    {
        $ok = $this->svc->delete((int)$p['id']);
        return $this->json(['deleted' => $ok], $ok ? 204 : 404);
    }
}
