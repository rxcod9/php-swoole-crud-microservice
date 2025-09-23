<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Metrics;
use OpenApi\Attributes as OA;
use Prometheus\RenderTextFormat;

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
            )
        ]
    )]
    public function check(): array
    {
        try {
            $renderer = new RenderTextFormat();
            $metrics = $renderer->render(Metrics::reg()->getMetricFamilySamples());

            return $this->text($metrics, 200, RenderTextFormat::MIME_TYPE);
        } catch (\Throwable $e) {
            return $this->text($e->getMessage(), 500, RenderTextFormat::MIME_TYPE);
        }
    }
}
