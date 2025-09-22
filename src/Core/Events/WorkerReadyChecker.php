<?php

namespace App\Core\Events;

use App\Core\Contexts\AppContext;

final class WorkerReadyChecker
{
    public function wait(int $timeoutMs = 2000): void
    {
        $waited = 0;
        while (!AppContext::isWorkerReady() && $waited < $timeoutMs) {
            echo "Waitinng for worker to be ready...";
            usleep(10000);
            $waited += 10;
        }

        if ($waited >= $timeoutMs) {
            throw new \RuntimeException("Worker not ready");
        }
    }
}
