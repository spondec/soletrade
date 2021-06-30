<?php

namespace App\Trade\Exchange;

trait MappableTrait
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

        foreach ($this->map as $key => $value)
        {
            if (!in_array($key, $this->expectedKeys))
            {
                $missingKeys[] = $key;
            }
        }

        if (!empty($missingKeys))
        {
            throw new \UnexpectedValueException('Expected keys are missing from the map: '
                . implode(' , ', $missingKeys));
        }
    }

    public function data(): array
    {
        return $this->data;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$this->map[$name]];
    }
}