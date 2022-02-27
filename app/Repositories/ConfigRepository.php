<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ConfigRepository extends Repository
{
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('trade');
    }

    public function getIndicators(): array
    {
        return $this->config['indicators'];
    }

    public function getStrategies(): array
    {
        return $this->config['strategies'];
    }

    public function getSymbols(): array
    {
        return \array_map(static function (string $exchange) {
            return DB::table('symbols')
                ->distinct()
                ->where('exchange_id', $exchange::instance()->model()->id)
                ->get('symbol')
                ->pluck('symbol')
                ->toArray();
        }, \array_combine(\array_map(static fn($e) => $e::name(),
            $exchanges = $this->getExchanges()), $exchanges));
    }

    public function getExchanges(): array
    {
        return \array_map(static fn(array $details) => $details['class'], $this->config['exchanges']) ?? [];
    }
}