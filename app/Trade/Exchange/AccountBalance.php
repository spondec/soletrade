<?php

namespace App\Trade\Exchange;

use App\Trade\Log;

class AccountBalance
{
    /** @var Asset[] */
    protected array $assets;

    public function __construct(protected AbstractExchange $exchange,
                                array                      $assets,
                                protected string           $relativeAsset = 'USDT')
    {
        foreach ($assets as $asset)
        {
            if (!$asset instanceof Asset)
            {
                throw new \InvalidArgumentException('The argument must be a instance of Asset class.');
            }

            $this->assets[$asset->name()] = $asset;
        }
    }

    public function calculateRoe(AccountBalance $prevBalance): array
    {
        $roe = [];

        foreach ($prevBalance->getAssets() as $asset)
        {
            $total = $asset->total();
            $roe[$name = $asset->name()] = $total / ($this->assets[$name]->total() - $total) * 100;
        }

        return $roe;
    }

    public function calculateRelativeWorth(string $relativeAsset = null, bool $onlyAvailable = false): array
    {
        $relativeAsset ??= $this->relativeAsset;

        if (empty($relativeAsset))
            throw new \UnexpectedValueException('Relative asset can not be empty.');

        $symbols = $this->exchange->symbols($relativeAsset);
        $worth = [];

        foreach ($this->assets as $asset)
        {
            if (($baseAsset = $asset->name()) != $relativeAsset)
            {
                $symbol = $this->exchange->buildSymbol($baseAsset, $relativeAsset);

                if (!in_array($symbol, $symbols))
                {
                    continue;
                }

                try
                {
                    $price = $this->exchange->orderBook($symbol)->bestAsk();

                } catch (\UnexpectedValueException $e)
                {
                    Log::log($e);
                    continue;
                }
            }

            $worth[$baseAsset] = ($price ?? 1) * ($onlyAvailable ? $asset->available() : $asset->total());
        }

        arsort($worth);

        return $worth;
    }

    public function primaryAsset(): Asset
    {
        return $this->assets[array_key_first($this->calculateRelativeWorth(onlyAvailable: true))];
    }

    /**
     * @return Asset[]
     */
    public function getAssets(): array
    {
        return $this->assets;
    }
}