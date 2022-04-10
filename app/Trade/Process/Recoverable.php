<?php

declare(strict_types=1);

namespace App\Trade\Process;

class Recoverable
{
    public function __construct(protected readonly \Closure $process,
                                public    readonly int $retryInSeconds,
                                public    readonly int $retryLimit,
                                public    readonly array $handle = [])
    {
        if (!$this->handle)
        {
            throw new \LogicException('No Throwable to handle.');
        }
    }

    public function run(): mixed
    {
        return $this->try($this->retryInSeconds, $this->retryLimit);
    }

    protected function try(int $retryInSeconds, int $retryLimit): mixed
    {
        try
        {
            return ($this->process)();
        } catch (\Throwable $e)
        {
            if ($this->retryLimit > 0 && $this->isHandled($e))
            {
                sleep($this->retryInSeconds);
                $this->handle($e);

                $retryLimit--;
                if ($retryLimit < 0)
                {
                    throw $e;
                }
                return $this->try($retryInSeconds, $retryLimit);
            }

            throw $e;
        }
    }

    private function isHandled(\Throwable $e): bool
    {
        foreach ($this->handle as $throwable)
        {
            if ($e::class === $throwable || is_subclass_of($e, $throwable))
            {
                return true;
            }
        }

        return false;
    }

    protected function handle(\Throwable $e): void
    {

    }
}