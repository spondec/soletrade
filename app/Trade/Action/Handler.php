<?php

namespace App\Trade\Action;

use App\Models\TradeAction;
use App\Trade\Evaluation\Position;
use App\Trade\Evaluation\Price;
use App\Trade\HasConfig;
use App\Trade\HasName;

abstract class Handler
{
    use HasConfig;
    use HasName;

    /**
     * Return the available configuration for stubs.
     *
     * @return array
     */
    abstract public static function getStubConfig(): array;

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

    public function run(\stdClass $candle, int $priceDate): ?TradeAction
    {
        if (!$this->isTaken && $this->performAction($candle, $priceDate))
        {
            if ($this->config('lock'))
            {
                $this->applyLocks();
            }

            $this->isTaken = true;
            $this->action->is_taken = true;
            $this->action->timestamp = $priceDate;
            return $this->action;
        }

        return null;
    }

    /**
     * Return true if the action is taken.
     *
     * @param \stdClass $candle
     * @param int       $priceDate
     *
     * @return bool
     */
    abstract protected function performAction(\stdClass $candle, int $priceDate): bool;

    protected function applyLocks(): void
    {

    }

    protected function getDefaultConfig(): array
    {
        return [
            'lock' => true
        ];
    }

    protected function lockIfUnlocked(Price $price): void
    {
        if (!$price->isLocked())
        {
            $price->lock();
        }
    }

    protected function prepareReason(string $reason): string
    {
        return 'Trade action "' . static::name() . '" is taken: ' . $reason;
    }
}