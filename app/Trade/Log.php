<?php

declare(strict_types=1);

namespace App\Trade;

use App\Http\Middleware\ExecTimeMiddleware;
use Illuminate\Support\Facades\Log as Logger;

final class Log
{
    /**
     * @var \Throwable[]
     */
    protected static $errors = [];

    /** @var string[] */
    protected static array $tasks = [];

    public static function error(\Throwable $e)
    {
        static::$errors[] = $e;
    }

    public static function getErrors(): array
    {
        return static::$errors;
    }

    public static function info(string $message)
    {
        Logger::info(ExecTimeMiddleware::getSessionPrefix() . $message);
    }

    public static function execTimeStart(string $taskName): void
    {
        if (\in_array($taskName, static::$tasks))
        {
            throw new \LogicException("Task $taskName is already started.");
        }

        static::$tasks[(string)\microtime(true)] = $taskName;
        static::info(\sprintf('Started: %s', $taskName));
    }

    public static function execTimeFinish(string $taskName)
    {
        if (!$time = \array_search($taskName, static::$tasks))
        {
            throw new \LogicException("$taskName is not started, therefore can not be finished.");
        }

        $execTime = \microtime(true) - (float)$time;

        static::info(\sprintf('Finished in %s seconds: %s',
            \round($execTime, 2), static::$tasks[$time]));

        unset(static::$tasks[$time]);
    }
}