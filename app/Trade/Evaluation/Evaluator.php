<?php
/** @noinspection UnnecessaryCastingInspection */
/** @noinspection PhpCastIsUnnecessaryInspection */

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\TradeSetup;
use App\Trade\Calc;
use App\Trade\Repository\SymbolRepository;
use App\Trade\Strategy\Strategy;
use Illuminate\Support\Facades\App;

class Evaluator
{
    protected SymbolRepository $symbolRepo;

    public function __construct(protected Strategy $strategy)
    {
        $this->symbolRepo = App::make(SymbolRepository::class);
    }

    public function evaluate(TradeSetup $entry, TradeSetup $exit): Evaluation
    {
        $evaluation = $this->setup($entry, $exit);
        $this->realize($evaluation);

        return $evaluation->updateUniqueOrCreate()
            ->setRelation('entry', $entry)
            ->setRelation('exit', $exit);
    }

    protected function setup(TradeSetup $entry, TradeSetup $exit): Evaluation
    {
        $evaluation = new Evaluation();

        $evaluation->entry()->associate($entry);
        $evaluation->exit()->associate($exit);

        $this->assertEntryExitTime($evaluation);

        return $evaluation;
    }

    protected function assertEntryExitTime(Evaluation $evaluation): void
    {
        if ($evaluation->exit->timestamp <= $evaluation->entry->timestamp)
        {
            throw new \LogicException('Exit date must not be newer than or equal to entry trade.');
        }
    }

    protected function newLoop(TradeSetup $entry): TradeLoop
    {
        return new TradeLoop($entry,
            $this->strategy->evaluationSymbol(),
            $this->strategy->config('evaluation.loop'));
    }

    protected function realize(Evaluation $evaluation): void
    {
        $loop = $this->newLoop($evaluation->entry);
        $loop->setExitTrade($evaluation->exit);

        $status = $loop->run();

        $this->fill($evaluation, $status);
    }

    protected function fill(Evaluation $e, TradeStatus $status): void
    {
        $e->entry_price = $status->getEntryPrice()->get();
        $e->stop_price = $status->getStopPrice()?->get();
        $e->target_price = $status->getTargetPrice()?->get();
        $e->symbol()->associate($this->strategy->evaluationSymbol());

        $log = [];

        if ($position = $status->getPosition())
        {
            $e->is_entry_price_valid = true;
            $e->entry_timestamp = $position->entryTime();
            $e->is_ambiguous = $status->isAmbiguous() || $position->entryTime() === $position->exitTime();
            $e->used_size = $position->getMaxUsedSize();

            if (!$e->is_ambiguous)
            {
                $this->fillClosedPositionFields($position, $e);
                $this->fillPivots($e);
                $this->fillHighLowRoi($e);
            }

            $log['position'] = [
                'price_history' => [
                    'entry' => $status->getEntryPrice()->log()->toArray(),
                    'exit'  => $status->getTargetPrice()?->log()?->toArray() ?? [],
                    'stop'  => $status->getStopPrice()?->log()?->toArray() ?? []
                ],
                'transactions'  => $position->transactionLog()->toArray()
            ];
        }
        else
        {
            $e->entry_timestamp = null;
            $e->highest_entry_price = null;
            $e->lowest_entry_price = null;
            $e->is_ambiguous = null;
            $e->is_entry_price_valid = false;
            $e->is_stopped = null;
            $e->is_closed = null;
            $e->relative_roi = null;
            $e->exit_timestamp = null;
            $e->exit_price = null;
        }

        $e->log = $log;
    }

    protected function fillHighLowRoi(Evaluation $evaluation): void
    {
        if (!$evaluation->is_entry_price_valid || $evaluation->is_ambiguous)
        {
            return;
        }

        $entryPrice = (float)$evaluation->entry_price;
        $isBuy = $evaluation->entry->isBuy();

        $evaluation->highest_roi = Calc::roi($isBuy, $entryPrice, (float)($isBuy
            ? $evaluation->highest_price
            : $evaluation->lowest_price));
        $evaluation->lowest_roi = Calc::roi($isBuy, $entryPrice, (float)(!$isBuy
            ? $evaluation->highest_price
            : $evaluation->lowest_price));
    }

    protected function fillPivots(Evaluation $e): void
    {
        $entryPivots = $this
            ->symbolRepo
            ->assertLowestHighestCandle($e->symbol_id,
                $e->entry->price_date,
                $e->entry_timestamp);

        $e->highest_entry_price = $entryPivots['highest']->h;
        $e->lowest_entry_price = $entryPivots['lowest']->l;

        $pivots = $this
            ->symbolRepo
            ->assertLowestHighestCandle($e->symbol_id,
                $e->entry_timestamp,
                $e->exit_timestamp);

        $e->highest_price = $pivots['highest']->h;
        $e->lowest_price = $pivots['lowest']->l;
    }

    protected function fillClosedPositionFields(Position $position, Evaluation $e): void
    {
        $e->is_stopped = $position->isStopped();
        $e->is_closed = $position->isClosed();
        $e->relative_roi = $position->relativeExitRoi();
        $e->exit_timestamp = $position->exitTime();
        $e->exit_price = $position->getExitPrice();
    }
}