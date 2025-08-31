<?php

namespace App\Core;

final class AppContext {
    private static bool $workerReady = false;

    public static function isWorkerReady(): bool {
        return self::$workerReady;
    }

    public static function setWorkerReady(bool $ready): void {
        self::$workerReady = $ready;
    }
}