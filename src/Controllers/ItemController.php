<?php

/**
 * src/Controllers/ItemController.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Controllers/ItemController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Messages;
use App\Models\Item;
use App\Services\ItemService;
use OpenApi\Attributes as OA;

/**
 * RESTful Item resource controller
 * Handles CRUD operations for Item entities.
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class ItemController extends Controller
{
    public const TAG = 'ItemController';

    /**
     * Inject ItemService for business logic operations.
     */
    public function __construct(private readonly ItemService $itemService)
    {
        //
    }

    /**
     * Create a new item.
     * Expects JSON body with item data.
     *
     * @return array<string, mixed> Created item data
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
            new OA\Response(response: 400, description: 'Invalid input'),
        ]
    )]
    public function create(): array
    {
        $data = $this->request->getPostParams();
        $item = $this->itemService->create($data);
        return $this->json($item, 201);
    }

    /**
     * List items with optional pagination.
     * Query params: limit (default 100), offset (default 0)
     *
     * @return array<string, mixed> List of items with pagination info
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
                    type: 'string', // allowed columns
                    default: 'id',
                    enum: ['id', 'sku', 'created_at', 'updated_at']
                )
            ),
            new OA\Parameter(
                name: 'sortDirection',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string', // allowed columns
                    default: 'DESC',
                    enum: ['ASC', 'DESC']
                )
            ),
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
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'sku', type: 'string'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'price', type: 'number', format: 'float'),
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
        // Pagination params
        $page   = (int)($this->request->get('page', 1));
        $limit  = max(1, min((int)($this->request->get('limit', 20)), 100));
        $offset = max(0, ($page - 1) * $limit);

        // Override with direct offset if provided
        $limit  = (int)($this->request->get('limit', $limit));
        $offset = (int)($this->request->get('offset', $offset));

        $filters = [
            'sku'            => $this->request->get('sku'),
            'title'          => $this->request->get('title'),
            'created_after'  => $this->request->get('created_after'),
            'created_before' => $this->request->get('created_before'),
        ];

        $sortBy        = $this->request->get('sortBy', 'id');
        $sortDirection = $this->request->get('sortDirection', 'DESC');

        // Fetch from service if not cached
        [$records, $pagination] = $this->itemService->pagination([
            'limit'   => $limit,
            'offset'  => $offset,
            'filters' => array_filter($filters, fn (?string $v): bool => $v !== null),
            'sortBy'  => $sortBy,
            'sortDir' => $sortDirection,
        ]);

        $data = [
            'data'       => array_map(static fn (Item $item): array => $item->toArray(), $records),
            'pagination' => $pagination,
        ];

        // Respond with item list
        return $this->json($data);
    }

    /**
     * Show a single item by ID.
     * URL param: id
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Item data
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
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'sku', type: 'string'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'price', type: 'number', format: 'float'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function show(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $id   = (int)$params['id'];
        $item = $this->itemService->find($id);

        return $this->json($item->toArray());
    }

    /**
     * Show a single item by ID.
     * URL param: id
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Item data
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
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'sku', type: 'string'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'price', type: 'number', format: 'float'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function showBySku(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['sku']);
        $sku  = urldecode($params['sku']);
        $item = $this->itemService->findBySku($sku);

        return $this->json($item);
    }

    /**
     * Update a item by ID.
     * URL param: id
     * Expects JSON body with updated item data.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Updated item data
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
            ),
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
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function update(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $id   = (int)$params['id'];
        $data = $this->request->getPostParams();
        // Calling find to validate if entiry exists
        $this->itemService->find($id);
        $item = $this->itemService->update($id, $data);

        return $this->json($item->toArray());
    }

    /**
     * Delete a item by ID.
     * URL param: id
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Deletion status
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
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Item deleted'),
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function destroy(array $params): array
    {
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $id = (int)$params['id'];
        $ok = $this->itemService->delete($id);
        return $this->json(['deleted' => $ok], $ok ? 204 : 404);
    }
}
