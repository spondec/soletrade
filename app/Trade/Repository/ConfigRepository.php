<?php

namespace App\Trade\Repository;

use App\Models\Exchange;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;

class ConfigRepository extends Repository
{
    public readonly array $config;

    /**
     * @var string[]
     */
    public readonly array $indicators;
    /**
     * @var string[]
     */
    public readonly array $strategies;
    /**
     * @var array<string,array>
     */
    public readonly array $symbols;
    /**
     * @var string[]
     */
    public readonly array $exchanges;

    public readonly array $options;

    public function __construct()
    {
        $this->config = Config::get('trade');

        $this->options = $this->config['options'];
        $this->indicators = get_indicators();
        $this->strategies = get_strategies();

        $this->exchanges = $this->getExchanges();
        $this->symbols = $this->getSymbols();
    }

    #[ArrayShape(['class'     => 'string',
        'apiKey'              => 'string',
        'secretKey'           => 'string', ])]
    public function exchangeConfig(string $exchangeName): array
    {
        return $this->config['exchanges'][$exchangeName];
    }

    protected function getSymbols(): array
    {
        return Exchange::query()
            ->whereIn('class', $this->exchanges)
            ->get()
            ->keyBy('name')
            ->map(static fn (Exchange $exchange) => DB::table('symbols')
                ->distinct()
                ->where('exchange_id', $exchange->id)
                ->get('symbol')
                ->pluck('symbol')
                ->all())
            ->all();
    }

    protected function getExchanges(): array
    {
        return \array_map(static fn (array $details) => $details['class'], $this->config['exchanges']) ?? [];
    }
}
