<?php

/**
 * src/Core/Logger.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Logger.php
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
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
class Logger
{
    // Log levels
    public const DEBUG    = 100;

    public const INFO     = 200;

    public const WARNING  = 300;

    public const ERROR    = 400;

    public const CRITICAL = 500;

    // Channel for log messages
    private readonly Channel $channel;

    // Flag to indicate if the logger is running
    private bool $running = false;

    /**
     * Constructor
     *
     * @param string $logFile Path to the log file
     * @param int $minLevel Minimum log level to record
     * @param int $bufferSize Size of the channel buffer
     */
    public function __construct(private readonly string $logFile = '/app/logs/app.log', private readonly int $minLevel = self::DEBUG, int $bufferSize = 10000)
    {
        $this->channel = new Channel($bufferSize);
    }

    /**
     * Start the logger coroutine to process log messages.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
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

                // $level = $log['level'] ?? self::DEBUG;
                $line = json_encode($log, JSON_UNESCAPED_SLASHES) . PHP_EOL;
                file_put_contents($this->logFile, $line, FILE_APPEND);
            }
        });
    }

    /**
     * Stop the logger coroutine.
     */
    public function stop(): void
    {
        $this->running = false;
        $this->channel->close();
    }

    /**
     * Log a message with a given level.
     *
     * @param int $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
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

    /**
     * Log a debug message.
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message.
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message.
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log a critical message.
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context data
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Get the string representation of a log level.
     *
     * @param int $level Log level
     * @return string Log level name
     */
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
