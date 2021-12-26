<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

final class TradeLoop
{
    use HasConfig;

    protected array $config = [
        'stopAtExit'    => true,
        'timeout'       => 1440,
        'logRiskReward' => true
    ];

    protected SymbolRepository $repo;

    protected int $startDate;
    protected ?int $timeoutDate = null;

    protected ?int $lastRunDate = null;

    protected Collection $candles;

    protected \stdClass $firstCandle;

    protected TradeStatus $status;

    protected ?int $timeout;
    protected bool $logRiskReward;

    public function __construct(protected TradeSetup $entry, protected Symbol $evaluationSymbol, array $config)
    {
        $this->mergeConfig($config);
        $this->assertTradeSymbolMatchesEvaluationSymbol();
        $this->status = new TradeStatus($entry);
        $this->repo = App::make(SymbolRepository::class);

        $this->firstCandle = $this->getFirstCandle($this->entry);
        $this->startDate = $this->firstCandle->t;

        $this->timeout = $this->config('timeout');
        $this->logRiskReward = $this->config('logRiskReward');
    }

    protected function assertTradeSymbolMatchesEvaluationSymbol(): void
    {
        if ($this->entry->symbol->symbol !== $this->evaluationSymbol->symbol)
        {
            throw new \InvalidArgumentException('Evaluation symbol name does not match with the TradeSetup symbol name.');
        }
    }

    protected function getFirstCandle(TradeSetup $setup): \stdClass
    {
        return $this->repo->fetchNextCandle($this->evaluationSymbol, $setup->price_date) //candle is closed
            ?? DB::table('candles')
                 ->where('symbol_id', $this->evaluationSymbol->id)
                 ->where('t', '>=', $setup->timestamp)
                 ->where('t', '<=', $setup->price_date)
                 ->orderBy('t', 'DESC')
                 ->first(); //not closed
    }

    public function getLastRunDate(): int
    {
        return $this->lastRunDate;
    }

    public function runToExit(TradeSetup $exit): TradeStatus
    {
        $this->assertExitDateGreaterThanEntryDate($this->entry->price_date, $exit->price_date);

        $lastCandle = $this->repo->assertNextCandle($this->evaluationSymbol, $exit->price_date);
        $candles = $this->getCandlesBetween($lastCandle->t);

        $this->runLoop($candles);

        $position = $this->getPosition();

        if ($position && $position->isOpen())
        {
            $candle = $this->getLastCandle();
            if ($this->config('stopAtExit'))
            {
                $this->stopPositionAtClosePrice($position, $candle, 'Stopping at exit setup.');
            }
            else if ($this->shouldContinue($this->getPriceDate($candle, null)))
            {
                $this->continue($this->timeoutDate);
            }
        }

        return $this->status;
    }

    protected function getCandlesBetween(int $endDate): Collection
    {
        if ($this->lastRunDate)
        {
            return $this->repo->assertCandlesBetween($this->evaluationSymbol, $this->lastRunDate, $endDate);
        }
        return $this->repo->assertCandlesBetween($this->evaluationSymbol, $this->firstCandle->t, $endDate, includeStart: true);
    }

    protected function getCandlesLimit(int $limit): Collection
    {
        if ($this->lastRunDate)
        {
            return $this->repo->assertCandlesLimit($this->evaluationSymbol, $this->lastRunDate, limit: $limit);
        }
        return $this->repo->assertCandlesLimit($this->evaluationSymbol, $this->firstCandle->t, limit: $limit, includeStart: true);
    }

    public function run(int $chunk = 100): TradeStatus
    {
        do
        {
            $candles = $this->getCandlesLimit($chunk);
            $this->runLoop($candles);
        } while ($candles->first());

        return $this->status;
    }

    protected function nextCandle(\stdClass $candle): ?\stdClass
    {
        return $this->repo->fetchNextCandle($this->evaluationSymbol->id, $candle->t);
    }

    protected function assertExitDateGreaterThanEntryDate(int $startDate, int $endDate): void
    {
        if ($endDate <= $startDate)
        {
            throw new \LogicException('End date must not be newer than or equal to start date.');
        }
    }

    protected function runLoop(Collection $candles): void
    {
        if (!$first = $candles->first())
        {
            throw new \LogicException('Can not loop through an empty set.');
        }

        $evaluationSymbol = $this->evaluationSymbol;

        if ($first->symbol_id != $evaluationSymbol->id)
        {
            throw new \InvalidArgumentException('Invalid candles provided.');
        }

        $iterator = $candles->getIterator();

        $entry = $this->status->getEntryPrice();
        $exit = $this->status->getClosePrice();
        $stop = $this->status->getStopPrice();

        while ($iterator->valid())
        {
            $candle = $nextCandle ?? $iterator->current();
            $key = $iterator->key();
            $iterator->next();
            $nextCandle = $iterator->current();

            $candle->l = (float)$candle->l;
            $candle->h = (float)$candle->h;
            $candle->t = (int)$candle->t;

            if (!$this->status->isEntered())
            {
                $this->entry->loadBindingPrice($entry, 'price', $candle->t, $evaluationSymbol);
                $this->status->updateLowestHighestEntryPrice($candle);
                $this->tryPositionEntry($candle, $nextCandle);
            }
            else
            {
                if ($this->logRiskReward)
                {
                    $this->status->logRiskReward($candle);
                }

                if (!$this->status->isExited())
                {
                    $this->entry->loadBindingPrice($stop, 'stop_price', $candle->t, $evaluationSymbol);
                    $this->entry->loadBindingPrice($exit, 'close_price', $candle->t, $evaluationSymbol);
                    $this->tryPositionExit($position ?? $position = $this->getPosition(), $candle, $nextCandle);

                    if ($this->timeout && $position->isOpen() && $this->hasPositionTimedOut($this->getPriceDate($candle, $nextCandle)))
                    {
                        $this->stopPositionAtClosePrice($position, $candle, 'Trade timed out. Stopping.');
                    }
                }
                else
                {
                    break;
                }
            }
        }

        $this->lastRunDate = $this->isLastCandle($candle) ? $candles[$key - 1]->t : $candle->t;
        $pivots = $this->fetchPivotsFromStartToLastRun();
        $this->status->updateHighestLowestPrice($pivots['highest'], $pivots['lowest']);
    }

    protected function isLastCandle(\stdClass $candle): bool
    {
        return (bool)$this->repo->fetchNextCandle($candle->symbol_id, $candle->t);
    }

    protected function tryPositionEntry(\stdClass $candle, ?\stdClass $nextCandle): void
    {
        if (Calc::inRange($this->status->getEntryPrice()->get(), $candle->h, $candle->l))
        {
            $this->status->enterPosition($this->getPriceDate($candle, $nextCandle));

            if (!$this->timeoutDate && $this->timeout && $position = $this->getPosition())
            {
                $this->timeoutDate = $position->entryTime() + $this->timeout * 60 * 1000;
            }
        }
    }

    protected function getPriceDate(\stdClass $candle, ?\stdClass $next): int
    {
        if ($next)
        {
            return $next->t - 1000;
        }

        if ($nextCandle = $this->nextCandle($candle))
        {
            return $nextCandle->t - 1000;
        }

        return $this->evaluationSymbol->last_update;
    }

    #[Pure] protected function getPosition(): ?Position
    {
        return $this->status->getPosition();
    }

    protected function tryPositionExit(Position $position, \stdClass $candle, ?\stdClass $nextCandle): void
    {
        if (!$this->status->checkIsExited())
        {
            $stopped = $this->status->checkIsStopped($candle);
            $closed = $this->status->checkIsClosed($candle);

            if ($stopped || $closed)
            {
                if (!$this->status->isAmbiguous())
                {
                    if ($stopped)
                    {
                        $position->stop($priceDate = $this->getPriceDate($candle, $nextCandle));
                    }
                    if ($closed)
                    {
                        $position->close($priceDate ?? $this->getPriceDate($candle, $nextCandle));
                    }
                }
            }
            else
            {
                $this->status->runTradeActions($candle, $this->getPriceDate($candle, $nextCandle));
            }
        }
    }

    #[Pure] protected function hasPositionTimedOut(int $priceDate): bool
    {
        return $this->timeoutDate <= $priceDate;
    }

    protected function stopPositionAtClosePrice(Position $position, \stdClass $candle, string $reason): void
    {
        $priceDate = $this->getPriceDate($candle, null);

        if ($stop = $position->price('stop'))
        {
            $stop->set((float)$candle->c, $priceDate, $reason, true);
        }
        else
        {
            $position->addStopPrice($stop = new Price((float)$candle->c, $priceDate));
            $stop->newLog($priceDate, $reason, true);
        }

        $position->stop($priceDate);
    }

    /**
     * @return \stdClass[]
     */
    #[ArrayShape(['lowest' => \stdClass::class, 'highest' => \stdClass::class])]
    protected function fetchPivotsFromStartToLastRun(): array
    {
        return $this->repo->assertLowestHighestCandle($this->evaluationSymbol->id, $this->startDate, $this->lastRunDate);
    }

    public function getLastCandle(): \stdClass
    {
        return $this->repo->fetchCandle($this->evaluationSymbol, $this->lastRunDate);
    }

    protected function shouldContinue(int $priceDate): bool
    {
        return $this->timeoutDate > $priceDate;
    }

    public function continue(int $endDate): void
    {
        $this->assertExitDateGreaterThanEntryDate($this->lastRunDate, $endDate);

        $candles = $this->getCandlesBetween($endDate);

        $this->runLoop($candles);
    }

    public function status(): TradeStatus
    {
        return $this->status;
    }
}