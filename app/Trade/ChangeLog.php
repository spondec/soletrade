<?php

declare(strict_types=1);

namespace App\Trade;

class ChangeLog
{
    protected array $log = [];

    public function __construct(protected mixed $value)
    {
        $this->new($this->value, 0, '');
    }

    public function new(mixed $value, int $timestamp, string $reason): void
    {
        $log = [
            'value'     => $value,
            'timestamp' => $timestamp ?: null,
            'reason'    => $reason ?: null,
        ];

        $last = end($this->log);

        if ($last !== $log)
        {
            if ($last && $last['timestamp'] > $timestamp)
            {
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
}