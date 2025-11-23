<?php

/**
 * src/Controllers/AsyncUserController.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Controllers/AsyncUserController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Channels\ChannelManager;
use App\Core\Controller;
use App\Core\Messages;
use App\Tasks\User\CreateUserTask;
use App\Tasks\User\DeleteUserTask;
use App\Tasks\User\UpdateUserTask;
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
final class AsyncUserController extends Controller
{
    public const TAG = 'AsyncUserController';

    /**
     * Inject UserService for business logic operations.
     */
    public function __construct(
        private readonly ChannelManager $channelManager
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
        path: '/async-users',
        summary: 'Create user async',
        tags: ['AsyncUsers'],
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
            new OA\Response(
                response: 202,
                description: 'Accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            description: 'Descriptive status message explaining the current state or outcome of the async request.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'jobId',
                            description: 'Unique identifier assigned to the asynchronous job or task for tracking and polling.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'result',
                            description: 'Optional numeric result or status code returned once the async job has completed.',
                            type: 'integer',
                            nullable: true
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid input'),
        ]
    )]
    public function create(): array
    {
        $start  = microtime(true);
        $data   = $this->request->getPostParams();
        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'PostParams loaded'));

        // Dispatch async user creation task
        $id = bin2hex(random_bytes(8));

        $result = $this->channelManager->push([
            'class'     => CreateUserTask::class,
            'id'        => $id,
            'arguments' => [__FUNCTION__, $data],
        ]);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push called'));

        // check if unable to push
        if ($result === false) {
            return $this->json([
                'message' => Messages::ERROR_INTERNAL_ERROR,
                'jobId'   => $id,
                'result'  => $result,
            ], 500);
        }

        $response = $this->json([
            'message' => 'User creation request accepted for asynchronous processing.',
            'jobId'   => $id,
            'result'  => $result,
        ], 202);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'response sent'));
        return $response;
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
        path: '/async-users/{id}',
        summary: 'Update user async',
        tags: ['AsyncUsers'],
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
            new OA\Response(
                response: 202,
                description: 'Accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            description: 'Descriptive status message explaining the current state or outcome of the async request.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'jobId',
                            description: 'Unique identifier assigned to the asynchronous job or task for tracking and polling.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'result',
                            description: 'Optional numeric result or status code returned once the async job has completed.',
                            type: 'integer',
                            nullable: true
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function update(array $params): array
    {
        $start = microtime(true);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);
        $data        = $this->request->getPostParams();
        $queryTimeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[SQL] [%s] => Time: %f ms %s', __FUNCTION__, $queryTimeMs, 'PostParams loaded'));

        // Dispatch async user creation task
        $id = bin2hex(random_bytes(8));

        $result = $this->channelManager->push([
            'class'     => UpdateUserTask::class,
            'id'        => $id,
            'arguments' => [__FUNCTION__, $params, $data],
        ]);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push called'));

        // check if unable to push
        if ($result === false) {
            return $this->json([
                'message' => Messages::ERROR_INTERNAL_ERROR,
                'jobId'   => $id,
                'result'  => $result,
            ], 500);
        }

        $response = $this->json([
            'message' => 'User updation request accepted for asynchronous processing.',
            'jobId'   => $id,
            'result'  => $result,
        ], 202);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'response sent'));
        return $response;
    }

    /**
     * Delete a user by ID.
     *
     * @param array<string, string|null> $params Route parameters
     *
     * @return array<string, mixed> Deletion status
     */
    #[OA\Delete(
        path: '/async-users/{id}',
        summary: 'Delete user async',
        tags: ['AsyncUsers'],
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
                response: 202,
                description: 'Accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            description: 'Descriptive status message explaining the current state or outcome of the async request.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'jobId',
                            description: 'Unique identifier assigned to the asynchronous job or task for tracking and polling.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'result',
                            description: 'Optional numeric result or status code returned once the async job has completed.',
                            type: 'integer',
                            nullable: true
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: Messages::RESOURCE_NOT_FOUND),
        ]
    )]
    public function destroy(array $params): array
    {
        $start = microtime(true);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, 'called #' . $params['id']);

        // Dispatch async user creation task
        $id = bin2hex(random_bytes(8));

        $result = $this->channelManager->push([
            'class'     => DeleteUserTask::class,
            'id'        => $id,
            'arguments' => [__FUNCTION__, $params],
        ]);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push called'));

        // check if unable to push
        if ($result === false) {
            return $this->json([
                'message' => Messages::ERROR_INTERNAL_ERROR,
                'jobId'   => $id,
                'result'  => $result,
            ], 500);
        }

        $response = $this->json([
            'message' => 'User deletion request accepted for asynchronous processing.',
            'jobId'   => $id,
            'result'  => $result,
        ], 202);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'response sent'));
        return $response;
    }
}
