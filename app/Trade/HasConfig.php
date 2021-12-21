<?php

namespace App\Trade;

trait HasConfig
{
    protected function mergeConfig(array &$config): void
    {
        $this->resolveKeys($config);
        $this->resolveKeys($this->config);

        if (method_exists($this, 'getDefaultConfig'))
        {
            $defaultConfig = $this->getDefaultConfig();       //defined by an abstract class, preferably
            $childConfig = $this->config;                     //defined by the instance class or inherited

            //new config keys from the child class via $config property are expected
            //so do not try to match keys here
            $this->config = array_merge_recursive_distinct($defaultConfig, $childConfig);
        }

        //new config keys are not allowed, match keys now
        $this->assertKeyMatch($this->config, $config);
        $this->config = array_merge_recursive_distinct($this->config, $config);
    }

    protected function resolveKeys(array &$array): array
    {
        foreach ($array as $key => $value)
        {
            $keys = explode('.', $key);

            if (isset($keys[1])) //has multiple dimensions
            {
                foreach ($keys as $k)
                {
                    if (!isset($ref))
                    {
                        $ref = &$array[$k];
                    }
                    else
                    {
                        $ref = &$ref[$k];
                    }

                    $ref = $ref ?? [];
                }

                $ref = $value;

                unset($array[$key]);
                unset($ref);
            }
        }

        return $array;
    }

    protected function assertKeyMatch(array &$original, array &$replacement): void
    {
        foreach ($replacement as $key => &$value)
        {
            if (!array_key_exists($key, $original))
            {
                throw new \UnexpectedValueException("Config key does match: $key");
            }

            if (is_array($value) && !array_is_list($value))
            {
                $this->assertKeyMatch($original[$key], $value);
            }
        }
    }

    public function config(string $key, bool $assertNotNull = false): mixed
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

        if ($assertNotNull && $value === null)
        {
            throw new \LogicException("Config value of '$key' can not be null.");
        }

        return $value;
    }
}