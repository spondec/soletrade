<?php

namespace App\Trade\Process;

use App\Trade\Log;
use App\Trade\Repository\ConfigRepository;

class RecoverableRequest extends Recoverable
{
    protected function __construct(\Closure $process, int $retryInSeconds, int $retryLimit, array $handle = [])
    {
        parent::__construct($process, $retryInSeconds, $retryLimit, $handle);
    }

    public static function new(
        \Closure $request,
        ?int     $retryInSeconds = null,
        ?int     $retryLimit = null,
        array    $handle = []
    ): static
    {
        $config = \App::make(ConfigRepository::class)->options['recoverableRequest'];

        return new static($request,
            $retryInSeconds ?? $config['retryInSeconds'],
            $retryLimit ?? $config['retryLimit'],
            \array_merge($handle, $config['handle']));
    }

    protected function handle(\Throwable $e): void
    {
        Log::error($e);
    }
}
