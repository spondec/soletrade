<?php

namespace App\Trade\Exchange\Account;

use App\Trade\Exchange\Exchange;
use App\Trade\Log;
use JetBrains\PhpStorm\Pure;

class Balance
{
    /** @var Asset[] */
    protected array $assets;

    /**
     * @param Exchange     $exchange
     * @param array<Asset> $assets
     * @param string       $relativeAsset
     */
    public function __construct(protected Exchange $exchange,
                                array              $assets,
                                protected string   $relativeAsset = 'USDT')
    {
        foreach ($assets as $asset)
        {
            if (!$asset instanceof Asset)
            {
                throw new \InvalidArgumentException('Assets must be instances of Asset class.');
            }

            if ($asset->available() == 0 && $asset->total() == 0)
            {
                continue;
            }

            $this->assets[$asset->name()] = $asset;
        }
    }

    #[Pure] public function calculateRoi(Balance $prevBalance): array
    {
        $roe = [];

        foreach ($prevBalance->assets() as $asset)
        {
            $total = $asset->total();
            $roe[$name = $asset->name()] = $total / ($this->assets[$name]->total() - $total) * 100;
        }

        return $roe;
    }

    public function calculateRelativeNetWorth(string $relativeAsset = null, bool $onlyAvailable = false): array
    {
        $relativeAsset ??= $this->relativeAsset;

        if (empty($relativeAsset))
        {
            throw new \UnexpectedValueException('Relative asset can not be empty.');
        }

        $symbols = $this->exchange->fetch()->symbols($relativeAsset);
        $worth = [];

        foreach ($this->assets as $asset)
        {
            if (($baseAsset = $asset->name()) != $relativeAsset)
            {
                $symbol = $this->exchange->fetch()->symbol($baseAsset, $relativeAsset);

                if (!\in_array($symbol, $symbols))
                {
                    continue;
                }

                try
                {
                    $price = $this->exchange->fetch()->orderBook($symbol)->bestAsk();

                } catch (\UnexpectedValueException $e)
                {
                    Log::log($e);
                    continue;
                }
            }

            $worth[$baseAsset] = ($price ?? 1) * ($onlyAvailable ? $asset->available() : $asset->total());
        }

        \arsort($worth);

        return $worth;
    }

    public function primaryAsset(): Asset
    {
        return $this->assets[\array_key_first($this->calculateRelativeNetWorth(onlyAvailable: true))];
    }

    /**
     * @return Asset[]
     */
    public function assets(): array
    {
        return $this->assets;
    }
}