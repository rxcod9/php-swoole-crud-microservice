<?php

/**
 * src/Controllers/HealthController.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Controllers/HealthController.php
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Constants;
use App\Core\Controller;
use App\Tables\TableWithLRUAndGC;
use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Swoole\Http\Server;
use Swoole\Table;

/**
 * HealthController handles health check endpoints for the application.
 * Provides both JSON and HTML responses to monitor the status of worker processes,
 * including database and cache connection pool usage.
 *
 * @category  Controllers
 * @package   App\Controllers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class HealthController extends Controller
{
    public function __construct(
        private readonly Server $server,
        private readonly Table $table,
        private readonly TableWithLRUAndGC $tableWithLRUAndGC
    ) {
        //
    }

    /**
     * Health check JSON endpoint.
     * Returns the health status of the service including worker and cache stats.
     *
     * @return array<string,mixed>
     */
    #[OA\Get(
        path: '/health',
        summary: 'Health Check',
        description: 'Health check endpoint to verify the service is running.',
        tags: ['Home'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'ok', type: 'bool'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function check(): array
    {
        $data      = $this->getWorkerData();
        $cacheData = $this->getCacheData();

        return $this->json([
            'ok'            => true,
            'uptime'        => Carbon::now()->getTimestamp() - ($_SERVER['REQUEST_TIME'] ?? Carbon::now()->getTimestamp()),
            'ts'            => Carbon::now()->getTimestamp(),
            'pid'           => posix_getpid(),
            'workers_count' => count($data),
            'workers'       => $data,
            'cache'         => $this->tableWithLRUAndGC->stats(),
            'cacheCount'    => count($cacheData),
            'cacheData'     => $cacheData,
            'server'        => $this->server->stats(),
        ]);
    }

    /**
     * Health check HTML endpoint.
     *
     * @return array<string,mixed>
     */
    public function checkHtml(): array
    {
        return $this->html(
            $this->renderHtml()
        );
    }

    /**
     * Renders the health check HTML page.
     * This method now only assembles the sections and delegates HTML building.
     */
    private function renderHtml(): string
    {
        // Render individual sections
        $workerHtml      = $this->renderWorkerHtml();
        $cacheHtml       = $this->renderCacheHtml();
        $workerStatsHtml = $this->renderTableStatsHtml($this->table, 'Workers Table');
        $cacheStatsHtml  = $this->renderTableStatsHtml($this->tableWithLRUAndGC, 'Cache Table');
        $serverStatsHtml = $this->renderServerStatsHtml();

        // Render full HTML page
        return $this->buildHtmlPage(
            title: 'Health Check',
            body: '
                <h1>Health Check</h1>
                <p>Uptime: ' . secondsReadable(Carbon::now()->getTimestamp() - ($_SERVER['REQUEST_TIME'] ?? Carbon::now()->getTimestamp())) . '</p>
                <p>Timestamp: ' . date(Constants::DATETIME_FORMAT) . '</p>
                <p>PID: ' . posix_getpid() . "</p>
                {$workerStatsHtml}
                {$cacheStatsHtml}
                {$serverStatsHtml}
                {$workerHtml}
                {$cacheHtml}"
        );
    }

    /**
     * Builds the full HTML document with <head> and <body>.
     * Extracted to reduce length of renderHtml().
     */
    private function buildHtmlPage(string $title, string $body): string
    {
        return "<!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>{$title}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    tr:nth-child(even) { background-color: #f9f9f9; }
                    .alive { color: green; font-weight: bold; }
                    .dead { color: red; font-weight: bold; }
                    pre.json {
                        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, \"Roboto Mono\", monospace;
                        white-space: pre;
                        overflow: auto;
                        max-height: 60vh;
                        padding: 1rem;
                        border-radius: 8px;
                        background: #0f1720;
                        color: #e6edf3;
                    }
                </style>
            </head>
            <body>
            {$body}
            </body>
            </html>";
    }

    /**
     * @return list<non-empty-array<mixed>>
     */
    private function getWorkerData(): array
    {
        $data = [];
        foreach ($this->table as $wid => $row) {
            $data[$wid] = [
                ...$row,
                'alive' => (Carbon::now()->getTimestamp() - $row['last_heartbeat'] < 10),
                'since' => (Carbon::now()->getTimestamp() - $row['last_heartbeat'] < 10)
                    ? (Carbon::now()->getTimestamp() - $row['last_heartbeat']) . 's'
                    : 'dead',
                'uptime' => secondsReadable(Carbon::now()->getTimestamp() - ($row['first_heartbeat'] ?? Carbon::now()->getTimestamp())),
            ];
        }

        usort($data, fn (array $a, array $b): int => ($b['mysql_in_use'] <=> $a['mysql_in_use']) !== 0 ? $b['mysql_in_use'] <=> $a['mysql_in_use'] : $b['redis_in_use'] <=> $a['redis_in_use']);

        return $data;
    }

    /**
     * Renders the worker HTML table.
     */
    private function renderWorkerHtml(): string
    {
        $data = $this->getWorkerData();
        $rows = $this->getWorkerRowsHtml($data);

        return '<p>Workers Count: ' . count($data) . "</p>
            <table>{$rows}</table>";
    }

    /**
     * Renders the worker rows HTML.
     *
     * @param list<non-empty-array<mixed>> $data
     */
    private function getWorkerRowsHtml(array $data): string
    {
        $rows = '<tr>
            <th>Worker ID</th>
            <th>PID</th>
            <th>First Heartbeat</th>
            <th>Last Heartbeat</th>
            <th>Status</th>
            <th>Uptime</th>
            <th>MySQL Capacity</th>
            <th>MySQL Available</th>
            <th>MySQL Created</th>
            <th>MySQL In Use</th>
            <th>Redis Capacity</th>
            <th>Redis Available</th>
            <th>Redis Created</th>
            <th>Redis In Use</th>
        </tr>';

        foreach ($data as $wid => $row) {
            $statusClass = (bool)$row['alive'] ? 'alive' : 'dead';
            $statusText  = (bool)$row['alive'] ? 'Alive' : 'Dead';
            $rows .= "<tr>
                <td>{$wid}</td>
                <td>{$row['pid']}</td>
                <td>" . Carbon::createFromTimestamp($row['first_heartbeat'])->format(Constants::DATETIME_FORMAT) . '</td>
                <td>' . Carbon::createFromTimestamp($row['last_heartbeat'])->format(Constants::DATETIME_FORMAT) . "</td>
                <td class=\"{$statusClass}\">{$statusText}</td>
                <td>" . secondsReadable(Carbon::now()->getTimestamp() - ($row['first_heartbeat'] ?? Carbon::now()->getTimestamp())) . "</td>
                <td>{$row['mysql_capacity']}</td>
                <td>{$row['mysql_available']}</td>
                <td>{$row['mysql_created']}</td>
                <td>{$row['mysql_in_use']}</td>
                <td>{$row['redis_capacity']}</td>
                <td>{$row['redis_available']}</td>
                <td>{$row['redis_created']}</td>
                <td>{$row['redis_in_use']}</td>
            </tr>";
        }

        return $rows;
    }

    /**
     * @return list<non-empty-array<mixed>>
     */
    private function getCacheData(): array
    {
        $data = [];
        foreach ($this->tableWithLRUAndGC as $wid => $row) {
            $data[$wid] = [
                ...$row,
                'id'                   => $wid,
                'value'                => maybeDecodeJson($row['value']),
                'expires_at_readable'  => (($row['expires_at'] ?? Carbon::now()->getTimestamp()) > Carbon::now()->getTimestamp()) ? secondsReadable(($row['expires_at'] ?? Carbon::now()->getTimestamp()) - Carbon::now()->getTimestamp()) : 'Expired',
                'last_access_readable' => $row['last_access'] > 0 ? secondsReadable(Carbon::now()->getTimestamp() - $row['last_access']) : 'Never Accessed',
            ];
        }

        usort($data, fn (array $a, array $b): int => $b['last_access'] <=> $a['last_access']);

        return $data;
    }

    /**
     * Renders the cache HTML table.
     */
    private function renderCacheHtml(): string
    {
        $data = $this->getCacheData();
        $rows = $this->getCacheRowsHtml($data);

        return '<p>Caches Count: ' . count($data) . "</p>
            <table>{$rows}</table>";
    }

    /**
     * Renders the cache rows HTML.
     *
     * @param list<non-empty-array<mixed>> $data
     */
    private function getCacheRowsHtml(array $data): string
    {
        $rows = '<tr>
            <th>ID</th>
            <th>Value</th>
            <th>Last Access</th>
            <th>Usage</th>
            <th>Created At</th>
            <th>Expired At</th>
        </tr>';
        foreach ($data as $row) {
            $rows .= '<tr>
                <td>' . $row['id'] . '</td>
                <td>' . $this->getPrettyJson($row['value']) . '</td>
                <td>' . $row['last_access_readable'] . '</td>
                <td>' . $row['usage'] . '</td>
                <td>' . Carbon::createFromTimestamp($row['created_at'])->format(Constants::DATETIME_FORMAT) . '</td>
                <td>' . $row['expires_at_readable'] . '</td>
            </tr>';
        }

        return $rows;
    }

    /**
     * Formats JSON data in a pretty <pre> block.
     */
    private function getPrettyJson(mixed $data): string
    {
        $pretty = maybeEncodeJson($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return '<pre class="json"><code>' . htmlspecialchars($pretty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }

    /**
     * Renders the Swoole table stats HTML.
     */
    private function renderTableStatsHtml(Table|TableWithLRUAndGC $table, string $title = 'Table'): string
    {
        // 1. Stats (Swoole\Table::stats or TableWithLRUAndGC::stats)
        $stats     = $table->stats();
        $statsHtml = '<h3>' . $title . ' Stats</h3><table><tr><th>Property</th><th>Value</th></tr>';
        foreach ($stats as $k => $v) {
            $statsHtml .= sprintf('<tr><td>%s</td><td>%s</td></tr>', $k, $v);
        }

        $statsHtml .= '</table>';

        // 2. Table Size info
        $sizeHtml = '<h3>' . $title . ' Size</h3><table><tr><th>Size</th><th>Memory Size (bytes)</th></tr>';
        $sizeHtml .= '<tr><td>' . $table->size . '</td><td>' . bytesReadable($table->memorySize) . '</td></tr>';
        $sizeHtml .= '</table>';

        return $statsHtml . $sizeHtml;
    }

    /**
     * Renders the Swoole server stats HTML table.
     */
    private function renderServerStatsHtml(): string
    {
        $stats = $this->server->stats();
        if ($stats === []) {
            return '<p>No server stats available.</p>';
        }

        $html = '<h3>Server Stats</h3><table><tr><th>Property</th><th>Value</th></tr>';

        foreach ($stats as $key => $value) {
            // Format large numbers nicely
            if (is_int($value) && $value > 1000) {
                $value = number_format($value);
            }

            $html .= sprintf('<tr><td>%s</td><td>%s</td></tr>', htmlspecialchars($key), htmlspecialchars((string)$value));
        }

        return $html . '</table>';
    }
}
