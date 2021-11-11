<?php

namespace App\Trade;

trait HasConfig
{
    protected function mergeConfig(array &$config)
    {
        if (method_exists($this, 'getDefaultConfig'))
        {
            $defaultConfig = $this->getDefaultConfig();
            $childConfig = $this->config;

            $this->config = array_merge_recursive_distinct($defaultConfig, $childConfig);
            $this->config = array_merge_recursive_distinct($this->config, $config);
        }
        else
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