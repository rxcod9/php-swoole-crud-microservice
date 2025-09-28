<?php

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
 * @package App\Controllers
 * @version 1.0.0
 * @since 1.0.0
 * @author Your Name
 * @license MIT
 * @link https://your-repo-link
 */
final class MetricsController extends Controller
{
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
            $renderer = new RenderTextFormat();
            $metrics  = $renderer->render(Metrics::reg()->getMetricFamilySamples());

            return $this->text($metrics, 200, RenderTextFormat::MIME_TYPE);
        } catch (Throwable $e) {
            error_log('Exception: ' . $e->getMessage()); // logged internally
            return $this->text(Messages::ERROR_INTERNAL_ERROR, 500, RenderTextFormat::MIME_TYPE);
        }
    }
}
