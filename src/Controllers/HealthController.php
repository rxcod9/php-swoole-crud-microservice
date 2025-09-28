<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Constants;
use App\Core\Controller;
use App\Tables\TableWithLRUAndGC;

use function count;

use OpenApi\Attributes as OA;
use Swoole\Table;

/**
 * HealthController handles health check endpoints for the application.
 *
 * Provides both JSON and HTML responses to monitor the status of worker processes,
 * including database and cache connection pool usage.
 *
 * @package App\Controllers
 * @version 1.0.0
 * @since 1.0.0
 * @author Your Name
 * @license MIT
 * @link https://your-repo-link
 */
final class HealthController extends Controller
{
    public function __construct(
        private Table $table,
        private TableWithLRUAndGC $cacheTable
    ) {
        //
    }

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
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'ok', type: 'bool'),
                    ]
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
            'uptime'        => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
            'ts'            => time(),
            'pid'           => posix_getpid(),
            'workers_count' => count($data),
            'workers'       => $data,
            'cache'         => $this->cacheTable->stats(),
            'cacheCount'    => $cacheData,
            'cacheData'     => $cacheData,
        ]);
    }

    /**
     * Health check HTML endpoint.
     */
    public function checkHtml(): array
    {
        return $this->html(
            $this->renderHtml()
        );
    }

    /**
     * Renders the health check HTML page.
     */
    private function renderHtml(): string
    {
        // $stats      = json_encode((array) $this->table->stats(), JSON_PRETTY_PRINT);
        // $table      = json_encode((array) $this->table, JSON_PRETTY_PRINT);
        // $cacheStats = json_encode((array) $this->cacheTable->stats(), JSON_PRETTY_PRINT);
        // $cacheTable = json_encode((array) $this->cacheTable, JSON_PRETTY_PRINT);
        $workerHtml = $this->renderWorkerHtml();
        $cacheHtml  = $this->renderCacheHtml();

        $workerStatsHtml = $this->renderTableStatsHtml($this->table, 'Workers Table');
        $cacheStatsHtml  = $this->renderTableStatsHtml($this->cacheTable, 'Cache Table');

        return "<!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Health Check</title>
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
            <h1>Health Check</h1>
            <p>Uptime: " . secondsReadable(time() - ($_SERVER['REQUEST_TIME'] ?? time())) . '</p>
            <p>Timestamp: ' . date(Constants::DATETIME_FORMAT) . '</p>
            <p>PID: ' . posix_getpid() . "</p>
            {$workerStatsHtml}
            {$cacheStatsHtml}
            {$workerHtml}
            {$cacheHtml}
            </body>
            </html>";
    }

    private function getWorkerData()
    {
        $data = [];
        foreach ($this->table as $wid => $row) {
            $data[$wid] = [
                ...$row,
                'alive' => (time() - $row['last_heartbeat'] < 10),
                'since' => (time() - $row['last_heartbeat'] < 10)
                    ? (time() - $row['last_heartbeat']) . 's'
                    : 'dead',
                'uptime' => secondsReadable(time() - ($row['first_heartbeat'] ?? time())),
            ];
        }

        usort($data, fn ($a, $b) => $b['mysql_in_use'] <=> $a['mysql_in_use'] ?: $b['redis_in_use'] <=> $a['redis_in_use']);

        return $data;
    }

    /**
     * Renders the health check HTML page.
     */
    private function renderWorkerHtml(): string
    {
        $data = $this->getWorkerData();
        $rows = $this->getWorkerRowsHtml($data);

        return '<p>Workers Count: ' . count($data) . "</p>
            <table>{$rows}</table>";
    }

    /**
     * Renders the health check HTML page.
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
            $statusClass = $row['alive'] ? 'alive' : 'dead';
            $statusText  = $row['alive'] ? 'Alive' : 'Dead';
            $rows .= "<tr>
                <td>{$wid}</td>
                <td>{$row['pid']}</td>
                <td>" . date(Constants::DATETIME_FORMAT, $row['first_heartbeat']) . '</td>
                <td>' . date(Constants::DATETIME_FORMAT, $row['last_heartbeat']) . "</td>
                <td class=\"{$statusClass}\">{$statusText}</td>
                <td>" . secondsReadable(time() - ($row['first_heartbeat'] ?? time())) . "</td>
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

    private function getCacheData()
    {
        $data = [];
        foreach ($this->cacheTable as $wid => $row) {
            $data[$wid] = [
                ...$row,
                'id'                   => $wid,
                'value'                => json_decode($row['value']),
                'expires_at_readable'  => (($row['expires_at'] ?? time()) > time()) ? secondsReadable(($row['expires_at'] ?? time()) - time()) : 'Expired',
                'last_access_readable' => $row['last_access'] > 0 ? secondsReadable(time() - $row['last_access']) : 'Never Accessed',
            ];
        }

        usort($data, fn ($a, $b) => $b['last_access'] <=> $a['last_access']);

        return $data;
    }

    /**
     * Renders the Cache HTML page.
     */
    private function renderCacheHtml(): string
    {
        $data = $this->getCacheData();
        $rows = $this->getCacheRowsHtml($data);

        return '<p>Caches Count: ' . count($data) . "</p>
            <table>{$rows}</table>";
    }

    /**
     * Renders the Cache HTML page.
     */
    private function getCacheRowsHtml(array $data): string
    {
        $rows = '<tr>
            <th>ID</th>
            <th>Value</th>
            <th>Last Access</th>
            <th>Expired At</th>
        </tr>';
        foreach ($data as $row) {
            $rows .= '<tr>
                <td>' . $row['id'] . '</td>
                <td>' . $this->getPrettyJson($row['value']) . '</td>
                <td>' . $row['last_access_readable'] . '</td>
                <td>' . $row['expires_at_readable'] . '</td>
            </tr>';
        }

        return $rows;
    }

    private function getPrettyJson($data)
    {
        $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return '<pre class="json"><code>' . htmlspecialchars($pretty, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    }

    private function renderTableStatsHtml(Table|TableWithLRUAndGC $table, string $title = 'Table'): string
    {
        // 1. Stats (Swoole\Table::stats or TableWithLRUAndGC::stats)
        $stats     = $table->stats();
        $statsHtml = '<h3>' . $title . ' Stats</h3><table><tr><th>Property</th><th>Value</th></tr>';
        foreach ($stats as $k => $v) {
            $statsHtml .= "<tr><td>{$k}</td><td>{$v}</td></tr>";
        }
        $statsHtml .= '</table>';

        // 2. Size info (for Swoole\Table)
        $sizeHtml = '';

        $sizeHtml = '<h3>' . $title . ' Size</h3><table><tr><th>Size</th><th>Memory Size (bytes)</th></tr>';
        $sizeHtml .= '<tr><td>' . $table->size . '</td><td>' . bytesReadable($table->memorySize) . '</td></tr>';
        $sizeHtml .= '</table>';


        return $statsHtml . $sizeHtml;
    }
}
