<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Trade\Config;
use App\Trade\Exchange\AbstractExchange;

class ExchangeController extends Controller
{
    public function index(): array
    {
        return array_map(fn($v) => $v::instance()->info(), Config::exchanges());
    }

    public function symbols(string $exchange): array
    {
        if (in_array($exchange, Config::exchanges()))
        {
            /** @var AbstractExchange $exchange */
            $instance = $exchange::instance();

            return $instance->symbols();
        }

        throw new \HttpException("Exchange $exchange doesn't exist.");
    }

    public function balances(): array
    {
        $balances = [];

        foreach (Config::exchanges() as $exchange)
        {
            $exchange = $exchange::instance();
            $exchangeName = $exchange->name();

            foreach ($exchange->accountBalance()->getAssets() as $assetName => $asset)
            {
                $balances[] = [
                    'name'      => $assetName,
                    'exchange'  => $exchangeName,
                    'available' => $asset->available(),
                    'total'     => $asset->total(),
                ];
            }
        }
        $names = array_column($balances, 'name');
        array_multisort($names, SORT_ASC, $balances);
        return $balances;
    }
}
