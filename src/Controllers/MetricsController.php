<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Metrics;
use Prometheus\RenderTextFormat;
use Prometheus\CollectorRegistry;

final class MetricsController extends Controller
{
    public function index(): void
    {
        // $renderer = new RenderTextFormat();
        // $metrics  = $renderer->render(Metrics::reg()->getMetricFamilySamples());

        // $this->response->header('Content-Type', RenderTextFormat::MIME_TYPE);
        // $this->response->end($metrics);
    }
}
