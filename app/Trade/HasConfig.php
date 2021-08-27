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
}