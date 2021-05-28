<?php

namespace App\Trader\Exchange;

use App\Trader\Asset;

class AccountBalance
{
    /** @var Asset[] */
    protected array $assets;

    public function __construct(array $assets)
    {
        foreach ($assets as $asset)
        {
            if(!$asset instanceof Asset)
            {
                throw new \InvalidArgumentException('Passed argument must be a instance of Asset class.');
            }
        }

        $this->assets = $assets;
    }
}