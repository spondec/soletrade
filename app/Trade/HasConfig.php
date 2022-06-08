<?php

namespace App\Trade;

/**
 * @property array config
 */
trait HasConfig
{
    private readonly bool $hasDefaultConfig;
    protected array $variableConfigKeys = [];

    public function mergeConfig(array &$config): void
    {
        if (! isset($this->hasDefaultConfig))
        {
            $this->hasDefaultConfig = \method_exists(static::class, 'getDefaultConfig');
        }

        $this->assertPreRequisites();

        $this->buildDefaultConfig();
        $this->buildUserConfig($config);
    }

    private function resolveKeys(array &$array): array
    {
        foreach ($array as $key => $value)
        {
            $keys = \explode('.', $key);

            if (isset($keys[1]))
            { //has multiple dimensions
                foreach ($keys as $k)
                {
                    if (! isset($ref))
                    {
                        $ref = &$array[$k];
                    }
                    else
                    {
                        $ref = &$ref[$k];
                    }

                    $ref = $ref ?? []; //redundant?
                }

                $ref = $value;

                unset($array[$key]);
                unset($ref);
            }
        }

        return $array;
    }

    private function assertKeyMatch(array &$original, array &$replacement): void
    {
        foreach ($replacement as $key => &$value)
        {
            if (! \array_key_exists($key, $original))
            {
                throw new \UnexpectedValueException("Config key does not match: $key");
            }

            if (! \in_array($key, $this->variableConfigKeys))
            {
                if (\is_array($value) && ! \array_is_list($value))
                {
                    $this->assertKeyMatch($original[$key], $value);
                }
            }
        }
    }

    public function config(?string $key = null, bool $assertNotEmpty = false): mixed
    {
        if ($key === null)
        {
            return $this->config;
        }

        $keys = \explode('.', $key);
        $value = &$this->config;

        foreach ($keys as $k)
        {
            if (\array_key_exists($k, $value))
            {
                $value = &$value[$k];
            }
            else
            {
                throw new \InvalidArgumentException('Undefined config key: '.$k);
            }
        }

        if ($assertNotEmpty && empty($value))
        {
            throw new \LogicException("Config value of '$key' can not be null.");
        }

        return $value;
    }

    private function buildUserConfig(array &$config): void
    {
        $this->resolveKeys($config);

        $this->assertKeyMatch($this->config, $config);
        $this->config = array_merge_recursive_distinct($this->config, $config);
    }

    private function buildDefaultConfig(): void
    {
        $this->resolveKeys($this->config);

        if ($this->hasDefaultConfig)
        {
            //parent class' config defaults
            $defaultConfig = $this->getDefaultConfig();
            //instance class' config defaults, overrides parent class' defaults
            $childConfig = $this->config;

            //new config definitions can be made by the instance class
            //so do not try to match keys here
            $this->config = array_merge_recursive_distinct($defaultConfig, $childConfig);
        }
    }

    private function assertPreRequisites(): void
    {
        if ($parent = \get_parent_class(static::class))
        {
            if (\get_parent_class($parent))
            {
                throw new \LogicException('Only one level of parent config is allowed.');
            }

            if (! $this->hasDefaultConfig && \in_array(HasConfig::class, \class_uses($parent)))
            {
                throw new \LogicException('Parent class must implement getDefaultConfig() method.');
            }
        }
    }
}
