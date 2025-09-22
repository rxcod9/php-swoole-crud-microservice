<?php

namespace App\Controllers;

use App\Core\Controller;
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
    public function __construct(private Table $table)
    {
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
            )
        ]
    )]
    public function check(): array
    {
        $data = [];
        foreach ($this->table as $wid => $row) {
            $data[$wid] = [
                ...$row,
                "alive" => (time() - $row["last_heartbeat"] < 10),
                "since" => (time() - $row["last_heartbeat"] < 10)
                    ? (time() - $row["last_heartbeat"]) . "s"
                    : "dead",
                "uptime" => (time() - ($row['first_heartbeat'] ?? time())) . " seconds",
            ];
        }

        usort($data, fn($a, $b) => $b['mysql_in_use'] <=> $a['mysql_in_use'] ?: $b['redis_in_use'] <=> $a['redis_in_use']);

        return $this->json([
            'ok' => true,
            'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
            'ts' => time(),
            'pid' => posix_getpid(),
            'workers_count' => count($data),
            'workers' => $data,
        ]);
    }

    /**
     * Health check HTML endpoint.
     */
    public function checkHtml(): array
    {
        $data = [];
        foreach ($this->table as $wid => $row) {
            $data[$wid] = [
                ...$row,
                "alive" => (time() - $row["last_heartbeat"] < 10),
                "since" => (time() - $row["last_heartbeat"] < 10)
                    ? (time() - $row["last_heartbeat"]) . "s"
                    : "dead",
                "uptime" => (time() - ($row['first_heartbeat'] ?? time())) . " seconds",
            ];
        }

        usort($data, fn($a, $b) => $b['mysql_in_use'] <=> $a['mysql_in_use'] ?: $b['redis_in_use'] <=> $a['redis_in_use']);

        return $this->html($this->renderHtml($data));
    }

    /**
     * Renders the health check HTML page.
     */
    private function renderHtml(array $data): string
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
                <td>" . date('Y-m-d H:i:s', $row['first_heartbeat']) . "</td>
                <td>" . date('Y-m-d H:i:s', $row['last_heartbeat']) . "</td>
                <td class=\"{$statusClass}\">{$statusText}</td>
                <td>" . (time() - ($row['first_heartbeat'] ?? time())) . " seconds</td>
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
                </style>
            </head>
            <body>
            <h1>Health Check</h1>
            <p>Uptime: " . (time() - ($_SERVER['REQUEST_TIME'] ?? time())) . " seconds</p>
            <p>Timestamp: " . date('Y-m-d H:i:s') . "</p>
            <p>PID: " . posix_getpid() . "</p>
            <p>Workers Count: " . count($data)  . "</p>
            <table>{$rows}</table>
            </body>
            </html>";
    }
}
