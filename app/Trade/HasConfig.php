<?php

namespace App\Trade;

trait HasConfig
{
    public function mergeConfig(array $config)
    {
        if ($config)
        {
            $this->config = array_merge_recursive_distinct($this->config, $config);
        }
    }

    public function config(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = &$this->config;

        foreach ($keys as $k)
        {
            $value = &$value[$k];
        }

        return $value;
    }
}