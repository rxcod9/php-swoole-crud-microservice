<?php

/**
 * src/Core/Logger.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Logger.php
 */
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ChannelException;
use Carbon\Carbon;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * Class Logger
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
class Logger
{
    public const DEBUG = 100;

    public const INFO = 200;

    public const WARNING = 300;

    public const ERROR = 400;

    public const CRITICAL = 500;

    private readonly Channel $channel;

    private bool $running = false;

    public function __construct(private readonly string $logFile = '/app/logs/app.log', private readonly int $minLevel = self::DEBUG, int $bufferSize = 10000)
    {
        $this->channel = new Channel($bufferSize);
    }

    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;

        Coroutine::create(function (): void {
            while ($this->running) {
                $log = $this->channel->pop();
                if ($log === false) {
                    continue;
                }

                $line = json_encode($log, JSON_UNESCAPED_SLASHES) . PHP_EOL;

                // Non-blocking-ish: still file_put_contents, but off main coroutine
                file_put_contents('php://stdout', $line, FILE_APPEND);
                file_put_contents($this->logFile, $line, FILE_APPEND);
            }
        });
    }

    public function stop(): void
    {
        $this->running = false;
        $this->channel->close();
    }

    public function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->minLevel) {
            return;
        }

        $log = [
            'time'     => Carbon::now()->format('Y-m-d H:i:s'),
            'level'    => $this->getLevelName($level),
            'workerId' => defined('SWOOLE_WORKER_ID') ? SWOOLE_WORKER_ID : 'N/A',
            'cid'      => Coroutine::getCid(),
            'message'  => $message,
            'context'  => $context,
        ];

        $success = $this->channel->push($log);
        if ($success === false) {
            throw new ChannelException('Unable to push to channel' . PHP_EOL);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    private function getLevelName(int $level): string
    {
        return match ($level) {
            self::DEBUG    => 'DEBUG',
            self::INFO     => 'INFO',
            self::WARNING  => 'WARNING',
            self::ERROR    => 'ERROR',
            self::CRITICAL => 'CRITICAL',
            default        => 'UNKNOWN',
        };
    }
}
