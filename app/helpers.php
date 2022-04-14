<?php

if (!function_exists('array_merge_recursive_distinct'))
{

    /**
     * @param array<int|string, mixed> $array1
     * @param array<int|string, mixed> $array2
     *
     * @return array<int|string, mixed>
     */
    function array_merge_recursive_distinct(array &$array1, array &$array2): array
    {
        $merged = $array1;
        foreach ($array2 as $key => &$value)
        {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]))
            {
                $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
            }
            else
            {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}

if (!function_exists('recoverable'))
{
    function recoverable(\Closure $request,
                         ?int     $retryInSeconds = null,
                         ?int     $retryLimit = null,
                         array    $handle = []): \App\Trade\Process\RecoverableRequest
    {
        return \App\Trade\Process\RecoverableRequest::new($request, $retryInSeconds, $retryLimit, $handle);
    }
}

if (!function_exists('on_shutdown'))
{
    function on_shutdown(\Closure $callback)
    {
        static $callbacks = [];
        static $executed = new WeakMap();

        foreach ($callbacks as $c)
        {
            if ($callback === $c)
            {
                return;
            }
        }

        register_shutdown_function($callbacks[] = function () use ($callback, &$executed) {
            if (isset($executed[$callback]))
            {
                return;
            }
            $executed[$callback] = true;
            $callback();
        });

        pcntl_signal(SIGINT, function () use (&$callbacks) {
            foreach ($callbacks as $callback)
            {
                $callback();
            }
            exit;
        });
    }
}