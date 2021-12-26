<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\TradeAction;
use App\Models\TradeSetup;
use App\Trade\Calc;
use App\Trade\Strategy\TradeAction\AbstractTradeActionHandler;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class TradeStatus
{
    protected Price $entryPrice;
    protected ?Price $closePrice;
    protected ?Price $stopPrice;
    /** @var AbstractTradeActionHandler[] */
    protected Collection $actionHandlers;
    protected Collection $riskRewardHistory;
    protected ?float $lowestEntryPrice = null;
    protected ?float $highestEntryPrice = null;
    protected ?float $lowestPrice = null;
    protected ?float $highestPrice = null;
    protected bool $isBuy;
    protected bool $isEntered = false;
    protected bool $isExited = false;
    protected bool $isClosed = false;
    protected bool $isStopped = false;
    protected ?Position $position = null;

    public function __construct(protected TradeSetup $entry)
    {
        $this->isBuy = $this->entry->isBuy();

        $this->initPrices();

        $this->riskRewardHistory = new Collection();
        $this->actionHandlers = new Collection();
    }

    protected function initPrices(): void
    {
        $priceDate = $this->entry->price_date;

        $this->entryPrice = $this->newPrice((float)$this->entry->price, $priceDate);
        $this->closePrice = $this->entry->close_price ? $this->newPrice($this->entry->close_price, $priceDate) : null;
        $this->stopPrice = $this->entry->stop_price ? $this->newPrice($this->entry->stop_price, $priceDate) : null;
    }

    protected function newPrice(float $price, int $timestamp, ?\Closure $onChange = null): Price
    {
        return new Price($price, $timestamp, $onChange);
    }

    public function enterPosition(int $entryTime): void
    {
        if ($this->position)
        {
            throw new \LogicException('Already in a position');
        }

        $this->position = $this->newPosition($entryTime);
        $this->initActionHandlers();

        $this->isEntered = true;
    }

    protected function newPosition(int $entryTime): Position
    {
        return new Position($this->entry->isBuy(),
                            $this->entry->size,
                            $entryTime,
                            $this->entryPrice,
                            $this->getClosePrice(),
                            $this->getStopPrice());
    }

    #[Pure] public function getClosePrice(): ?Price
    {
        return $this->closePrice ?: $this->closePrice = $this->position?->price('exit');
    }

    #[Pure] public function getStopPrice(): ?Price
    {
        return $this->stopPrice ?: $this->stopPrice = $this->position?->price('stop');
    }

    protected function initActionHandlers(): void
    {
        foreach ($this->entry->actions->filter(static fn(TradeAction $action) => !$action->is_taken) as $action)
        {
            $this->actionHandlers[] = $this->newActionHandler($this->position, $action);
        }
    }

    protected function newActionHandler(Position $position, TradeAction $tradeAction): AbstractTradeActionHandler
    {
        return new $tradeAction->class($position, $tradeAction);
    }

    public function updateHighestLowestPrice(\stdClass $highest, \stdClass $lowest): void
    {
        if ($this->highestPrice === null || $highest->h > $this->highestPrice)
        {
            $this->highestPrice = (float)$highest->h;
        }
        if ($this->lowestPrice === null || $lowest->l < $this->lowestPrice)
        {
            $this->lowestPrice = (float)$lowest->l;
        }
    }

    public function runTradeActions(\stdClass $candle, int $priceDate): void
    {
        foreach ($this->actionHandlers as $key => $handler)
        {
            if ($action = $handler->run($candle, $priceDate))
            {
                $action->save();
                unset($this->actionHandlers[$key]);
            }
        }
    }

    public function checkIsStopped(\stdClass $candle): bool
    {
        $this->assertPosition();
        $stopPrice = $this->getStopPrice();
        if ($stopPrice && $this->isStopped = (Calc::inRange($stopPrice->get(),
                                                            $candle->h,
                                                            $candle->l) || $this->position?->isStopped()))
        {
            $this->isExited = true;
        }

        return $this->isStopped;
    }

    protected function assertPosition(): void
    {
        if (!$this->position)
        {
            throw new \LogicException('Position has not been initialized.');
        }
    }

    public function checkIsClosed(\stdClass $candle): bool
    {
        $this->assertPosition();
        $closePrice = $this->getClosePrice();
        if ($closePrice && $this->isClosed = (Calc::inRange($closePrice->get(),
                                                            $candle->h,
                                                            $candle->l) || $this->position?->isClosed()))
        {
            $this->isExited = true;
        }

        return $this->isClosed;
    }

    public function checkIsExited(): bool
    {
        if ($this->isStopped || $this->isClosed)
        {
            return true;
        }

        if ($this->position)
        {
            return ($this->isClosed = $this->position->isClosed()) || ($this->isStopped = $this->position->isStopped());
        }

        return false;
    }

    public function logRiskReward(\stdClass $candle): void
    {
        if (!$this->lowestPrice)
        {
            $this->lowestPrice = $candle->l;
            $newLow = true;
        }
        else
        {
            $newLow = $candle->l < $this->lowestPrice;
        }

        if (!$this->highestPrice)
        {
            $this->highestPrice = $candle->h;
            $newHigh = true;
        }
        else
        {
            $newHigh = $candle->h > $this->highestPrice;
        }

        if ($newLow || $newHigh)
        {
            if ($newLow)
            {
                $this->lowestPrice = $candle->l;
            }
            if ($newHigh)
            {
                $this->highestPrice = $candle->h;
            }

            $this->riskRewardHistory[$candle->t] = [
                'ratio'  => round(Calc::riskReward($this->isBuy,
                                                   $this->entryPrice->get(),
                                                   $this->isBuy ? $this->highestPrice : $this->lowestPrice,
                                                   $this->isBuy ? $this->lowestPrice : $this->highestPrice,
                                                   $highRoi,
                                                   $lowRoi), 2),
                'reward' => $highRoi,
                'risk'   => $lowRoi
            ];
        }
    }

    public function updateLowestHighestEntryPrice(\stdClass $candle): void
    {
        if ($this->lowestEntryPrice === null || $candle->l < $this->lowestEntryPrice)
        {
            $this->lowestEntryPrice = $candle->l;
        }
        if ($this->highestEntryPrice === null || $candle->h > $this->highestEntryPrice)
        {
            $this->highestEntryPrice = $candle->h;
        }
    }

    public function riskRewardHistory(): Collection
    {
        return $this->riskRewardHistory;
    }

    #[Pure] public function getEntryPrice(): Price
    {
        return $this->entryPrice;
    }

    public function getLowestPrice(): ?float
    {
        return $this->lowestPrice;
    }

    public function getHighestPrice(): ?float
    {
        return $this->highestPrice;
    }

    public function getLowestEntryPrice(): float
    {
        return $this->lowestEntryPrice;
    }

    public function getHighestEntryPrice(): float|int
    {
        return $this->highestEntryPrice;
    }

    public function getPosition(): ?Position
    {
        return $this->position;
    }

    #[Pure] public function isStopped(): bool
    {
        return !$this->isAmbiguous() ? $this->isStopped : false;
    }

    public function isAmbiguous(): bool
    {
        return $this->isStopped && $this->isClosed;
    }

    #[Pure] public function isClosed(): bool
    {
        return !$this->isAmbiguous() ? $this->isClosed : false;
    }

    public function isEntered(): bool
    {
        return $this->isEntered;
    }

    public function isExited(): bool
    {
        return $this->isExited;
    }
}