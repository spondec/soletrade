<?php

const INDICATOR_DIR = "app/Indicators/";
const INDICATOR_NAMESPACE = "\App\Indicators\\";

const STRATEGY_DIR = "app/Strategies/";
const STRATEGY_NAMESPACE = "\App\Strategies\\";

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
    function on_shutdown(\Closure $callback): void
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

if (!function_exists('indicator_exists'))
{
    function indicator_exists(string $indicatorName): bool
    {
        return class_exists(INDICATOR_NAMESPACE . $indicatorName);
    }
}

if (!function_exists('strategy_exists'))
{
    function strategy_exists(string $strategyName): bool
    {
        return class_exists(STRATEGY_NAMESPACE . $strategyName);
    }
}

if (!function_exists('get_strategy_class'))
{
    function get_strategy_class(string $strategyName): string
    {
        if (class_exists($class = STRATEGY_NAMESPACE . $strategyName))
        {
            return $class;
        }

        throw new \RuntimeException("Strategy $strategyName not found");
    }
}

if (!function_exists('get_indicator_class'))
{
    function get_indicator_class(string $indicatorName): string
    {
        if (class_exists($class = INDICATOR_NAMESPACE . $indicatorName))
        {
            return $class;
        }
        throw new \RuntimeException("Indicator $indicatorName not found");
    }
}

if (!function_exists('get_indicators'))
{
    function get_indicators(): array
    {
        $files = new Illuminate\Filesystem\Filesystem();

        $indicators = [];

        foreach ($files->allFiles(base_path(INDICATOR_DIR)) as $file)
        {
            $basename = $file->getBasename('.php');
            $indicators[$basename] = INDICATOR_NAMESPACE . $basename;
        }

        return $indicators;
    }
}

if (!function_exists('get_strategies'))
{
    function get_strategies(): array
    {
        $files = new Illuminate\Filesystem\Filesystem();

        $strategies = [];

        foreach ($files->allFiles(base_path(STRATEGY_DIR)) as $file)
        {
            $basename = $file->getBasename('.php');
            $strategies[$basename] = STRATEGY_NAMESPACE . $basename;
        }

        return $strategies;
    }
}