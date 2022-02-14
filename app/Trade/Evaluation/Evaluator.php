<?php
/** @noinspection UnnecessaryCastingInspection */
/** @noinspection PhpCastIsUnnecessaryInspection */

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\Signal;
use App\Models\TradeSetup;
use App\Repositories\SymbolRepository;
use App\Trade\Calc;
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
        $this->realizeToExit($evaluation);

        return $evaluation->updateUniqueOrCreate();
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

    protected function realizeToExit(Evaluation $evaluation): void
    {
        $status = $this->strategy
            ->newLoop($evaluation->entry)
            ->runToExit($evaluation->exit);

        $this->fillEvaluation($evaluation, $status);
    }

    protected function fillEvaluation(Evaluation $evaluation, TradeStatus $status): void
    {
        $evaluation->highest_price = $status->getHighestPrice();
        $evaluation->lowest_price = $status->getLowestPrice();
        $evaluation->entry_price = $status->getEntryPrice()->get();
        $evaluation->stop_price = $status->getStopPrice()?->get();
        $evaluation->close_price = $status->getClosePrice()?->get();

        $log = [];

        if ($position = $status->getPosition())
        {
            $evaluation->is_entry_price_valid = true;
            $evaluation->entry_timestamp = $position->entryTime();
            $evaluation->highest_entry_price = $status->getHighestEntryPrice();
            $evaluation->lowest_entry_price = $status->getLowestEntryPrice();
            $evaluation->is_ambiguous = $status->isAmbiguous();
            $evaluation->used_size = $position->getMaxUsedSize();

            if (!$evaluation->is_ambiguous)
            {
                if (!$position->isOpen())
                {
                    $evaluation->is_stopped = $position->isStopped();
                    $evaluation->is_closed = $position->isClosed();
                    $evaluation->relative_roi = $position->relativeExitRoi();

                    $evaluation->exit_timestamp = $position->exitTime();
                    $evaluation->exit_price = $position->getExitPrice();
                }

                $this->calcHighLowRoi($evaluation);
            }

            $log['position'] = [
                'price_history' => [
                    'entry' => $status->getEntryPrice()->log()->get(),
                    'exit'  => $status->getClosePrice()?->log()?->get() ?? [],
                    'stop'  => $status->getStopPrice()?->log()?->get() ?? []
                ],
                'transactions'  => $position->transactionLog()->get()
            ];
        }
        else
        {
            $evaluation->entry_timestamp = null;
            $evaluation->highest_entry_price = null;
            $evaluation->lowest_entry_price = null;
            $evaluation->is_ambiguous = null;
            $evaluation->is_entry_price_valid = false;
            $evaluation->is_stopped = null;
            $evaluation->is_closed = null;
            $evaluation->relative_roi = null;
            $evaluation->exit_timestamp = null;
            $evaluation->exit_price = null;
        }

        $evaluation->log = $log;
    }

    protected function calcHighLowRoi(Evaluation $evaluation): void
    {
        if (!$evaluation->is_entry_price_valid || $evaluation->is_ambiguous)
        {
            return;
        }

        $entryPrice = (float)$evaluation->entry_price;
        $buy = $evaluation->entry->side === Signal::BUY;

        $evaluation->highest_roi = Calc::roi($buy, $entryPrice, (float)($buy
            ? $evaluation->highest_price
            : $evaluation->lowest_price));
        $evaluation->lowest_roi = Calc::roi($buy, $entryPrice, (float)(!$buy
            ? $evaluation->highest_price
            : $evaluation->lowest_price));
    }
}