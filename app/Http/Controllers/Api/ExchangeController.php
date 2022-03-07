<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\ConfigRepository;
use App\Trade\Exchange\Exchange;

class ExchangeController extends Controller
{
    public function __construct(protected ConfigRepository $configRepo)
    {
    }

    public function index(): array
    {
        return \array_map(static fn($v) => $v::instance()->info(), $this->configRepo->exchanges);
    }

    /**
     * @param string|Exchange $exchange
     */
    public function symbols(string $exchange): array
    {
        if (\in_array($exchange, $this->configRepo->exchanges))
        {
            return $exchange::instance()->fetch()->symbols();
        }

        throw new \HttpException("Exchange $exchange doesn't exist.");
    }

    public function balances(): array
    {
        $balances = [];

        foreach ($this->configRepo->exchanges as $exchange)
        {
            /** @var Exchange $exchange */
            $exchange = $exchange::instance();
            $exchangeName = $exchange::name();

            foreach ($exchange->fetch()->balance()->assets as $assetName => $asset)
            {
                $balances[] = [
                    'name'      => $assetName,
                    'exchange'  => $exchangeName,
                    'available' => $asset->available(),
                    'total'     => $asset->total(),
                ];
            }
        }
        $names = \array_column($balances, 'name');
        \array_multisort($names, SORT_ASC, $balances);
        return $balances;
    }
}
