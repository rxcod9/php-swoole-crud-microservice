<?php

/**
 * src/Controllers/MetricsController.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Controllers/MetricsController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Messages;
use App\Core\Metrics;
use OpenApi\Attributes as OA;
use Prometheus\RenderTextFormat;
use Throwable;

/**
 * MetricsController handles metrics check endpoints for the application.
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://your-repo-link
 */
final class MetricsController extends Controller
{
    public const TAG = 'MetricsController';

    #[OA\Get(
        path: '/metrics',
        summary: 'Metrics Check',
        description: 'Metrics check endpoint to verify the service is running.',
        tags: ['Home'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation'
            ),
        ]
    )]
    public function check(): array
    {
        try {
            $renderTextFormat = new RenderTextFormat();
            $metrics          = $renderTextFormat->render(Metrics::reg()->getMetricFamilySamples());

            return $this->text($metrics, 200, RenderTextFormat::MIME_TYPE);
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
            return $this->text(Messages::ERROR_INTERNAL_ERROR, 500, RenderTextFormat::MIME_TYPE);
        }
    }
}
