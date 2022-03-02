<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\TradeAction;
use App\Models\TradeSetup;
use App\Trade\Action\Handler;
use App\Trade\Calc;
use App\Trade\HasInstanceEvents;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;

class TradeStatus
{
    use HasInstanceEvents;

    protected array $events = ['positionEntry'];

    /** @var Collection<Handler> */
    protected Collection $actionHandlers;

    protected Price $entryPrice;
    protected ?Price $targetPrice;
    protected ?Price $stopPrice;

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

        $this->actionHandlers = new Collection();
    }

    protected function initPrices(): void
    {
        $priceDate = $this->entry->price_date;

        $this->entryPrice = $this->newPrice((float)$this->entry->price, $priceDate);
        $this->targetPrice = $this->entry->target_price ? $this->newPrice($this->entry->target_price, $priceDate) : null;
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

        $this->position = new Position($this->entry->isBuy(),
            $this->entry->size,
            $entryTime,
            $this->entryPrice,
            $this->getTargetPrice(),
            $this->getStopPrice());

        $this->registerPositionListeners();
        $this->initActionHandlers();

        $this->isEntered = true;
        $this->fireEvent('positionEntry');
    }

    protected function registerPositionListeners(): void
    {
        $this->position->listen(eventName: 'close', onEvent: function () {
            $this->isExited = $this->isClosed = true;
        });
        $this->position->listen(eventName: 'stop', onEvent: function () {
            $this->isExited = $this->isStopped = true;
        });
    }

    public function getTargetPrice(): ?Price
    {
        return $this->targetPrice ?: $this->targetPrice = $this->position?->price('exit');
    }

    public function getStopPrice(): ?Price
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

    protected function newActionHandler(Position $position, TradeAction $tradeAction): Handler
    {
        return new $tradeAction->class($position, $tradeAction);
    }

    public function setExitPrice(float $price, int $priceDate): void
    {
        $this->position->addExitPrice($exitPrice = new Price($price, $priceDate));
        $exitPrice->newLog($priceDate, 'Exit price has been set.');
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
        $target = $this->getTargetPrice();
        if ($target && $this->isClosed = (Calc::inRange($target->get(),
                    $candle->h,
                    $candle->l) || $this->position?->isClosed()))
        {
            $this->isExited = true;
        }

        return $this->isClosed;
    }

    #[Pure] public function getEntryPrice(): Price
    {
        return $this->entryPrice;
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