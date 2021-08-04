<?php

namespace App\Trade\Indicator;

use App\Models\Symbol;
use App\Models\Signal;
use App\Trade\NameTrait;
use Illuminate\Support\Collection;

abstract class AbstractIndicator
{
    use NameTrait;

    protected array $config = [];
    protected array $data = [];
    private array $signals = [];

    public function __construct(protected Symbol     $symbol,
                                protected Collection $candles,
                                array                $config = [])
    {
        if ($config)
            $this->config = array_merge_recursive_distinct($this->config, $config);
        $this->data = $this->run();
    }

    abstract protected function run(): array;

    public function raw(): array
    {
        return $this->data;
    }

    protected function combineTimestamps(array $data): array
    {
        $timestamps = array_slice($this->timestamps(), ($length = count($data)) * -1, $length);

        return array_combine($timestamps, $data);
    }

    protected function closes(): array
    {
        return array_column($this->candles->all(), 'c');
    }

    protected function timestamps(): array
    {
        return array_column($this->candles->all(), 't');
    }

    protected function newSignal(Signal $signal): void
    {
        //TODO trigger listeners
        $this->signals[] = $signal;
    }

    /**
     *
     * @return Signal[]
     */
    public function signals(): array
    {
        return $this->signals;
    }

    public function symbol(): Symbol
    {
        return $this->symbol;
    }

    public function data(): array
    {
        return $this->data;
    }
}