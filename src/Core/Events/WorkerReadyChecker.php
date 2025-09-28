<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Contexts\AppContext;
use App\Exceptions\WorkerNotReadyException;

final class WorkerReadyChecker
{
    public function wait(int $timeoutMs = 2000): void
    {
        $waited = 0;
        while (!AppContext::isWorkerReady() && $waited < $timeoutMs) {
            echo 'Waiting for worker to be ready...' . PHP_EOL;
            usleep(10000);
            $waited += 10;
        }

        if ($waited >= $timeoutMs) {
            throw new WorkerNotReadyException('Worker not ready');
        }
    }
}
