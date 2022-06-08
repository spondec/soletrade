<?php

declare(strict_types=1);

namespace App\Trade;

use Illuminate\Contracts\Support\Arrayable;

class ChangeLog implements Arrayable
{
    protected array $log = [];

    public function __construct(protected mixed $value, int $timestamp, string $reason = 'Created')
    {
        $this->new($this->value, $timestamp, $reason);
    }

    public function new(mixed $value, int $timestamp, string $reason): void
    {
        $log = [
            'value' => $value,
            'timestamp' => $timestamp,
            'reason' => $reason ?: null,
        ];

        $last = \end($this->log);

        if ($last !== $log) {
            if ($last && $last['timestamp'] > $timestamp) {
                throw new \LogicException('New change date must be greater than last change date.');
            }

            $this->log[] = $log;
            $this->value = $value;
        }
    }

    public function get(): array
    {
        return $this->log;
    }

    public function last(): array
    {
        return \end($this->log);
    }

    public function first(): array
    {
        return \reset($this->log);
    }

    public function toArray()
    {
        foreach ($log = $this->log as $k => $item) {
            $log[$k]['time'] = \date('Y-m-d H:i:s', (int) (as_ms($item['timestamp']) / 1000));
        }

        return $log;
    }
}
