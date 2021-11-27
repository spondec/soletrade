<?php

declare(strict_types=1);

namespace App\Trade;

use App\Http\Middleware\ExecTimeMiddleware;
use Illuminate\Support\Facades\Log as Logger;

final class Log
{
    protected static array $log;

    /** @var string[] */
    protected static array $tasks = [];

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
            'message'   => $message ?: 'Empty message received.'
        ];
    }

    protected static function logInfo(string $message)
    {
        Logger::info(ExecTimeMiddleware::getSessionPrefix() . $message);
    }

    public static function execTimeStart(string $taskName): void
    {
        if (in_array($taskName, static::$tasks))
        {
            throw new \LogicException("Task $taskName is already started.");
        }

        static::$tasks[(string)microtime(true)] = $taskName;
        static::logInfo(sprintf('Started: %s', $taskName));
    }

    public static function execTimeFinish(string $taskName)
    {
        if (!$time = array_search($taskName, static::$tasks))
        {
            throw new \LogicException("$taskName is not started, therefore can not be finished.");
        }

        $execTime = microtime(true) - (float)$time;

        static::logInfo(sprintf('Finished in %s seconds: %s',
            round($execTime, 2), static::$tasks[$time]));

        unset(static::$tasks[$time]);
    }
}