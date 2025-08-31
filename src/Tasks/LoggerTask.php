<?php

namespace App\Tasks;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

final class LoggerTask
{
    public static function handle(array $payload): void
    {
        static $log = null;
        if (!$log) {
            $log = new Logger('access');
            $log->pushHandler(new StreamHandler('/app/logs/access.log'));
        }
        $log->info('request', $payload);
    }
}
