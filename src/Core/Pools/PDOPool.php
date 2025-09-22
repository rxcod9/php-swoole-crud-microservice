<?php

namespace App\Core\Pools;

use PDO;
use Swoole\Coroutine\Channel;

final class PDOPool
{
    private Channel $chan;
    private int $min;
    private int $max;
    private array $conf;
    private int $created = 0;
    public function __construct(array $conf, int $min, int $max)
    {
        $this->conf = $conf;
        $this->min = $min;
        $this->max = $max;
        $this->chan = new Channel($max);
        for ($i = 0; $i < $min; $i++) {
            $this->chan->push($this->make());
        }
    }
    private function make(): PDO
    {
        $pdo = new PDO($this->conf['dsn'], $this->conf['user'], $this->conf['pass'], $this->conf['options'] ?? []);
        $this->created++;
        return $pdo;
    }
    public function get(float $timeout = 1.0): PDO
    {
        if ($this->chan->isEmpty() && $this->created < $this->max) {
            return $this->make();
        }
        $pdo = $this->chan->pop($timeout);
        if (!$pdo) {
            throw new \RuntimeException('DB pool exhausted', 503);
        }
        try {
            $pdo->query('SELECT 1');
        } catch (\Throwable) {
            $pdo = $this->make();
        }
        return $pdo;
    }
    public function put(PDO $pdo): void
    {
        if (!$this->chan->isFull()) {
            $this->chan->push($pdo);
        }
    }
    public function size(): array
    {
        return ['size' => $this->chan->capacity,'available' => $this->chan->length(),'created' => $this->created];
    }
}
