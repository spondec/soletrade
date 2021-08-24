<?php

namespace App\Trade;

final class Log
{
    protected static array $log;

    public static function log(string|\Exception $message): void
    {
        if ($message instanceof \Exception)
        {
            $exception = get_class($message);
            $message = $message->getMessage();
        }

        static::$log[] = [
            'time'      => microtime(true),
            'exception' => $exception ?? null,
            'message'   => $message ?? 'Empty message received.'
        ];
    }

    public static function execTime(\Closure $closure, string $taskName): void
    {
        $time = microtime(true);
        $closure();
        \Illuminate\Support\Facades\Log::info(
            sprintf("%s lasted for %s seconds.",
                $taskName,
                round(microtime(true) - $time, 2)));
    }
}