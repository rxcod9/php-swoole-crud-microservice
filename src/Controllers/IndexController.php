<?php

/**
 * src/Controllers/IndexController.php
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

    /**
     * Returns OPcache status, memory statistics, and preload/warmup information.
     *
     * @return array<string,mixed> JSON response containing OPcache runtime details
     *
     * @phpdoc
     * This method is useful for debugging preload/warmup behavior in Swoole context.
     * Swoole workers DO NOT restart like FPM, so preloaded/warmup-compiled files
     * only apply on master startup. This function helps ensure your preload/warmup
     * script is running correctly during deployment.
     */
    #[OA\Get(
        path: '/opcache',
        summary: 'OPcache Status',
        description: 'Returns OPcache statistics including memory, scripts, hits, misses, and preload info.',
        tags: ['Home'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OPcache status response',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'enabled', type: 'boolean'),
                        new OA\Property(property: 'jit_enabled', type: 'boolean'),
                        new OA\Property(property: 'memory', type: 'object'),
                        new OA\Property(property: 'stats', type: 'object'),
                        new OA\Property(property: 'preloaded_count', type: 'integer'),
                        new OA\Property(property: 'warmup_compiled_count', type: 'integer'),
                        new OA\Property(property: 'scripts', type: 'array', items: new OA\Items(type: 'string')),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function opcacheStatus(): array
    {
        if (!function_exists('opcache_get_status')) {
            return $this->json([
                'enabled' => false,
                'message' => 'OPcache extension is not enabled in this runtime.',
            ]);
        }

        /** @var array<string,mixed>|false $status */
        $status = opcache_get_status(false);

        if ($status === false) {
            return $this->json([
                'enabled' => false,
                'message' => 'OPcache status unavailable or disabled.',
            ]);
        }

        $scriptInfo = $this->processScripts($status['scripts'] ?? []);

        return $this->json($this->buildResponsePayload($status, $scriptInfo));
    }

    /**
     * Processes the script list from OPcache status.
     *
     * @param array<string,mixed> $scripts
     * @return array<string,mixed>
     */
    private function processScripts(array $scripts): array
    {
        $scriptList     = [];
        $preloadedCount = 0;
        $warmupCount    = 0;

        foreach ($scripts as $path => $info) {
            $scriptList[] = $path;

            if (isset($info['preload']) && $info['preload'] === true) {
                $preloadedCount++;
            }

            if (isset($info['hits']) && $info['hits'] === 0) {
                $warmupCount++;
            }
        }

        return [
            'scriptList'     => $scriptList,
            'preloadedCount' => $preloadedCount,
            'warmupCount'    => $warmupCount,
        ];
    }

    /**
     * Builds the response payload for OPcache status.
     *
     * @param array<string,mixed> $status
     * @param array<string,mixed> $scriptInfo
     * @return array<string,mixed>
     */
    private function buildResponsePayload(array $status, array $scriptInfo): array
    {
        $memory = $status['memory_usage'] ?? [];
        $stats  = $status['opcache_statistics'] ?? [];

        return [
            'enabled'     => $status['opcache_enabled'] ?? false,
            'jit_enabled' => $status['jit']['enabled'] ?? false,
            'memory'      => [
                'used'           => $memory['used_memory'] ?? null,
                'free'           => $memory['free_memory'] ?? null,
                'wasted'         => $memory['wasted_memory'] ?? null,
                'wasted_percent' => $memory['current_wasted_percentage'] ?? null,
            ],
            'stats' => [
                'hits'               => $stats['hits'] ?? null,
                'misses'             => $stats['misses'] ?? null,
                'hit_rate'           => $stats['opcache_hit_rate'] ?? null,
                'num_cached_scripts' => $stats['num_cached_scripts'] ?? null,
                'max_cached_keys'    => $stats['max_cached_keys'] ?? null,
                'oom_restarts'       => $stats['oom_restarts'] ?? null,
                'hash_restarts'      => $stats['hash_restarts'] ?? null,
                'manual_restarts'    => $stats['manual_restarts'] ?? null,
            ],
            'preloaded_count'       => $scriptInfo['preloadedCount'],
            'warmup_compiled_count' => $scriptInfo['warmupCount'],
            'scripts'               => $scriptInfo['scriptList'],
        ];
    }
}
