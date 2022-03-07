<?php

namespace App\Trade\Exchange\Account;

use App\Trade\Exchange\Exchange;
use App\Trade\HasInstanceEvents;
use App\Trade\Log;
use JetBrains\PhpStorm\Pure;

class Balance implements \ArrayAccess
{
    use HasInstanceEvents;

    /** @var Asset[] */
    public readonly array $assets;
    protected array $events = ['update'];

    /**
     * @param Exchange $exchange
     * @param Asset[]  $assets
     * @param string   $relativeAsset
     */
    public function __construct(protected Exchange $exchange,
                                array              $assets,
                                protected string   $relativeAsset = 'USDT')
    {
        foreach ($assets as $k => $asset)
        {
            if (!$asset instanceof Asset)
            {
                throw new \InvalidArgumentException('Assets must be instances of Asset class.');
            }

            if ($asset->available() == 0 && $asset->total() == 0)
            {
                continue;
            }

            unset($assets[$k]);
            $assets[$asset->name] = $asset;
        }

        $this->assets = $assets;
    }

    public function update(): static
    {
        $update = $this->exchange->fetch()->balance();

        $this->relativeAsset = $update->relativeAsset;

        //asset update should be handled in this event
        $this->fireEvent('update', $update);
        return $this;
    }

    public function relativeAsset(): string
    {
        return $this->relativeAsset;
    }

    #[Pure] public function calculateRoi(Balance $prevBalance): array
    {
        $roe = [];

        foreach ($prevBalance->assets as $asset)
        {
            $total = $asset->total();
            $roe[$name = $asset->name] = $total / ($this->assets[$name]->total() - $total) * 100;
        }

        return $roe;
    }

    public function primaryAsset(): Asset
    {
        return $this->assets[\array_key_first($this->calculateRelativeNetWorth(onlyAvailable: true))];
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
            if (($baseAsset = $asset->name) != $relativeAsset)
            {
                $symbol = $this->exchange->fetch()->symbol($baseAsset, $relativeAsset);

                if (!\in_array($symbol, $symbols))
                {
                    continue;
                }

                try
                {
                    $price = $this->exchange->fetch()->orderBook($symbol)->bestAsk();

                } catch (\App\Exceptions\EmptyOrderBookException $e)
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

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->assets[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->assets[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->throwImmutableException();
    }

    protected function throwImmutableException(): never
    {
        throw new \LogicException('Balance is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->throwImmutableException();
    }
}