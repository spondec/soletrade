<?php

declare(strict_types=1);

namespace App\Trade\Order\Type;

use App\Models\Order;
use App\Trade\Enum;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Exchange\OrderManager;
use App\Trade\HasConfig;

abstract class Handler
{
    use HasConfig;

    protected array $config = [];

    protected function getDefaultConfig(): array
    {
        return [];
    }

    public function __construct(protected Side $side, protected OrderManager $manager, array $config = [])
    {
        $this->mergeConfig($config);
    }

    /**
     * @return class-string<Handler>
     */
    public static function getClass(OrderType $orderType): string
    {
        return '\App\Trade\Order\Type\\' . str(Enum::case($orderType))->lower()->studly();
    }

    public function order(
        OrderType $orderType,
        float     $quantity,
        float     $price,
        bool      $reduceOnly
    ): Order
    {
        if ($orderType !== $this->getOrderType()) {
            throw new \LogicException('Order type mismatch.');
        }

        return $this->handle($quantity, $price, $reduceOnly);
    }

    abstract public function getOrderType(): OrderType;

    abstract protected function handle(
        float $quantity,
        float $price,
        bool  $reduceOnly
    ): Order;

    protected function getSide(bool $reduceOnly): Side
    {
        return $reduceOnly ? $this->side->opposite() : $this->side;
    }
}
