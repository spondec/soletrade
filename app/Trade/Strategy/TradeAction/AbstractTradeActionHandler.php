<?php

namespace App\Trade\Strategy\TradeAction;

use App\Models\TradeAction;
use App\Trade\Evaluation\Position;
use App\Trade\Evaluation\Price;
use App\Trade\HasConfig;
use App\Trade\HasName;

abstract class AbstractTradeActionHandler
{
    use HasConfig;
    use HasName;

    protected array $config = [];
    protected array $required = [];

    protected bool $isTaken = false;

    public function __construct(protected Position $position, protected TradeAction $action)
    {
        $config = $this->action->config;
        $this->mergeConfig($config);
        $this->assertRequired();
        $this->setup();
    }

    protected function assertRequired(): void
    {
        foreach ($this->required as $key)
        {
            if (!$this->config($key))
            {
                throw new \InvalidArgumentException('Required config key is missing: ' . $key);
            }
        }
    }

    protected function setup(): void
    {

    }

    public function run(\stdClass $candle): ?TradeAction
    {
        if (!$this->isTaken && $this->performAction($candle))
        {
            if ($this->config('lock'))
            {
                $this->applyLocks();
            }

            $this->isTaken = true;
            $this->action->is_taken = true;
            $this->action->timestamp = $candle->t;
            return $this->action;
        }

        return null;
    }

    abstract protected function performAction(\stdClass $candle): bool;

    protected function applyLocks(): void
    {

    }

    protected function getDefaultConfig(): array
    {
        return [
            'lock' => true
        ];
    }

    protected function lockIfUnlocked(Price $price, object $locker): void
    {
        if (!$price->isLocked())
        {
            $price->lock($locker);
        }
    }

    protected function prepareReason(string $reason): string
    {
        return 'Trade action "' . static::name() . '" is taken: ' . $reason;
    }
}