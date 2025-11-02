<?php

/**
 * src/Core/Servers/WorkerManager.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/WorkerManager.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Contexts\AppContext;
use App\Core\Events\WorkerStartHandler;
use Swoole\Table;

/**
 * Class WorkerManager
 * Handles all worker operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class WorkerManager
{
    public function __construct(
        private readonly WorkerStartHandler $workerStartHandler,
        private readonly Table $healthTable
    ) {
        //
    }

    public function handleStop(int $workerId): void
    {
        echo "[WorkerStop] Worker #{$workerId} stopped\n";
        $this->workerStartHandler->clear($workerId);
        $this->disableWorker($workerId, $this->healthTable);
    }

    public function handleError(
        int $workerId,
        int $pid,
        int $exit,
        int $signal
    ): void {
        echo sprintf('[WorkerError] Worker #%d (PID: %d) crashed. Exit: %d, Signal: %d%s', $workerId, $pid, $exit, $signal, PHP_EOL);
        $this->workerStartHandler->clear($workerId);
        $this->disableWorker($workerId, $this->healthTable);
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function disableWorker(int $workerId, Table $table): void
    {
        AppContext::setWorkerReady(false);
        if ($table->exist((string)$workerId)) {
            $table->del((string)$workerId);
        }
    }
}
