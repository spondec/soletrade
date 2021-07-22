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
            'time' => microtime(true),
            'exception' => $exception ?? null,
            'message' => $message ?? 'Empty message received.'
        ];
    }
}