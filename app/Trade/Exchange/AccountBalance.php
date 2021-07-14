<?php

namespace App\Trade\Exchange;

use App\Trade\Errors;

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

    protected function calculateRelativeWorth()
    {
        $list = $this->exchange->symbolList($this->relativeAsset);
        $relativeWorth = [];

        foreach ($this->assets as $asset)
        {
            if (($baseAsset = $asset->name()) != $this->relativeAsset)
            {
                $symbol = $this->exchange->buildSymbol($baseAsset, $this->relativeAsset);

                if (!in_array($symbol, $list))
                {
                    continue;
                }

                try
                {
                    $price = $this->exchange->orderBook($symbol)->bestAsk();

                } catch (\UnexpectedValueException $e)
                {
                    Errors::log($e);
                    continue;
                }
            }

            $relativeWorth[$baseAsset] = ($price ?? 1) * $asset->available();
        }

        arsort($relativeWorth);

        return $relativeWorth;
    }

    public function primaryAsset(): Asset
    {
        return $this->assets[array_key_first($this->calculateRelativeWorth())];
    }
}