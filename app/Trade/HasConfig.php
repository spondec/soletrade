<?php

namespace App\Trade;

trait HasConfig
{
    protected function mergeConfig(array &$config)
    {
        if (method_exists($this, 'getDefaultConfig'))
        {
            $defaultConfig = $this->getDefaultConfig(); //defined by an abstract class, preferably
            $childConfig = $this->config;               //defined by the instance class or inherited

            //changes to config keys from the child class via $config property are expected
            //so do not try to match keys here
            $this->config = array_merge_recursive_distinct($defaultConfig, $childConfig);

            //additions to config keys are not allowed, will match keys now
            $this->assertKeyMatch($this->config, $config);
            $this->config = array_merge_recursive_distinct($this->config, $config);
        }
        else
        {
            $this->assertKeyMatch($this->config, $config);
            $this->config = array_merge_recursive_distinct($this->config, $config);
        }
    }

    protected function assertKeyMatch(array &$original, array &$replacement)
    {
        foreach ($replacement as $key => &$value)
        {
            if (!array_key_exists($key, $original))
            {
                throw new \UnexpectedValueException("Config key does match: $key");
            }

            if (is_array($value))
            {
                $this->assertKeyMatch($original[$key], $value);
            }
        }
    }

    public function config(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = &$this->config;

        foreach ($keys as $k)
        {
            if (array_key_exists($k, $value))
            {
                $value = &$value[$k];
            }
            else
            {
                throw new \InvalidArgumentException('Undefined config key: ' . $k);
            }
        }

        return $value;
    }
}