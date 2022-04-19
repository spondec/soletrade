<?php

declare(strict_types=1);

namespace App\Trade;

use App\Http\Middleware\ExecTimeMiddleware;
use Log as Logger;

final class Log
{
    /**
     * @var \Throwable[]
     */
    protected static array $errors = [];

    /** @var string[] */
    protected static array $tasks = [];

    public static function error(\Throwable $e): void
    {
        Logger::error($e);
        self::$errors[] = $e;
    }

    public static function getErrors(): array
    {
        return self::$errors;
    }

    public static function info(string|\Closure $message, mixed $expression = true): void
    {
        if (self::canLog() && $expression)
        {
            Logger::info(ExecTimeMiddleware::getSessionPrefix() . ($message instanceof \Closure ? $message() : $message));
        }
    }

    public static function execTimeStart(string $taskName): void
    {
        if (\in_array($taskName, self::$tasks))
        {
            throw new \LogicException("Task $taskName is already started.");
        }

        self::$tasks[(string)\microtime(true)] = $taskName;
        self::info(\sprintf('Started: %s', $taskName));
    }

    public static function execTimeFinish(string $taskName): void
    {
        if (!$time = \array_search($taskName, self::$tasks))
        {
            throw new \LogicException("$taskName is not started, therefore can not be finished.");
        }

        $execTime = \microtime(true) - (float)$time;

        self::info(\sprintf('Finished in %s seconds: %s',
            \round($execTime, 2), self::$tasks[$time]));

        unset(self::$tasks[$time]);
    }

    protected static function canLog(): bool
    {
        //does not log in tests to prevent unexpected mock call errors
        return !defined('PHPUNIT_COMPOSER_INSTALL') && !defined('__PHPUNIT_PHAR__');
    }
}