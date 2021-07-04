<?php

namespace App\Trade\Exchange;

abstract class AbstractMappable
{
    /**
     * @var string[]
     */
    protected array $expectedKeys = [];

    public function __construct(protected array $map,
                                protected array $data)
    {
        $this->assertExpectedKeys();
    }

    protected function assertExpectedKeys(): void
    {
        $missingKeys = [];

        foreach ($this->map + $this->data as $key => $value)
        {
            if (!in_array($key, $this->expectedKeys))
            {
                $missingKeys[] = $key;
            }
        }

        if (!empty($missingKeys))
        {
            throw new \UnexpectedValueException('Expected keys are missing from the data: '
                . implode(' ,', $missingKeys));
        }
    }

    public function data(): array
    {
        return $this->data;
    }

    public function __get(string $name): mixed
    {
        if (isset($this->map[$name]))
        {
            return $this->data[$this->map[$name]];
        }

        return $this->data[$name];
    }
}