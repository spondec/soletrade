<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExecTimeMiddleware
{
    protected static ?int $sessionId = null;

    public function __construct()
    {
        if (!static::$sessionId)
        {
            static::$sessionId = \random_int(1000000000, 9000000000);
        }
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        Log::info(static::getSessionPrefix() . 'Started...');
        return $next($request);
    }

    public static function getSessionPrefix(): string
    {
        return "[SESSION-" . static::$sessionId . "] ";
    }

    public function terminate()
    {
        if (\defined('LARAVEL_START'))
        {
            Log::info(\sprintf("%sExecution time: %s seconds.",
                static::getSessionPrefix(),
                \round(\microtime(true) - LARAVEL_START, 2)));
        }
    }
}
