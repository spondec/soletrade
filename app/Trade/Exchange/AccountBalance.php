<?php

namespace App\Trade\Exchange;

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
                throw new \TypeError('The argument must be a instance of Asset class.');
            }
        }

        $this->assets = $assets;
    }
}