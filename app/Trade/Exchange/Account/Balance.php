<?php

namespace App\Trade\Exchange\Account;

use App\Trade\Exchange\Exchange;
use App\Trade\HasInstanceEvents;
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

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->assets[$offset]);
    }

    public function offsetGet(mixed $offset): Asset
    {
        return $this->assets[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->throwImmutableException();
    }

    protected function throwImmutableException(): void
    {
        throw new \LogicException('Balance is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->throwImmutableException();
    }
}