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
use App\Trade\HasConfig;
use Illuminate\Support\Facades\App;

class Evaluator
{
    use HasConfig;

    protected SymbolRepository $symbolRepo;

    protected array $config = [
        'timeout'    => 1440, //trade duration in minutes - exceeding trades will be closed
        'stopAtExit' => true //close trade at exit setup
    ];

    public function __construct(array $config = [])
    {
        $this->mergeConfig($config);
        $this->symbolRepo = App::make(SymbolRepository::class);
    }

    public function evaluate(TradeSetup $entry, TradeSetup $exit): Evaluation
    {
        $evaluation = $this->setup($entry, $exit);
        $this->realizeWithExit($evaluation);

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

    protected function realizeWithExit(Evaluation $evaluation): void
    {
        $loop = new TradeLoop($evaluation->entry);

        $position = $loop->runToExit($evaluation->exit);

        if ($position)
        {
            $this->applyPositionLimitations($position, $evaluation, $loop);
        }

        $this->fillEvaluation($evaluation, $loop, $position);
    }

    protected function applyPositionLimitations(Position $position, Evaluation $evaluation, TradeLoop $loop): void
    {
        if ($position->isOpen())
        {
            if ($this->config('stopAtExit'))
            {
                $candle = $loop->getLastCandle();

                $position->price('stop')->set((float)$candle->c, 'Stopping at exit setup.');
                $position->stop((int)$candle->t);
            }
            else
            {
                $endDate = $evaluation->entry_timestamp + $this->config['timeout'] * 60 * 1000;

                if ($endDate > $loop->getLastRunDate())
                {
                    $loop->continue($endDate);
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if ($position->isOpen())
                {
                    $candle = $loop->getLastCandle();

                    $position->price('stop')->set((float)$candle->c, 'Trade timed out. Stopping.');
                    $position->stop((int)$candle->t);
                }
            }
        }
    }

    protected function fillEvaluation(Evaluation $evaluation, TradeLoop $loop, ?Position $position): void
    {
        $evaluation->highest_price = $loop->getHighestPrice();
        $evaluation->lowest_price = $loop->getLowestPrice();
        $evaluation->entry_price = $loop->getEntryPrice();
        $evaluation->stop_price = $loop->getStopPrice();
        $evaluation->close_price = $loop->getClosePrice();

        $log = [];

        if ($position)
        {
            $evaluation->is_entry_price_valid = true;
            $evaluation->entry_timestamp = $position->entryTime();
            $evaluation->highest_entry_price = $loop->getHighestEntryPrice();
            $evaluation->lowest_entry_price = $loop->getLowestEntryPrice();
            $evaluation->is_ambiguous = $loop->isAmbiguous();
            $evaluation->risk_reward_history = $loop->riskRewardHistory();

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

                $this->calcHighestLowestPricesToExit($evaluation);
                $this->calcHighLowRealRoi($evaluation);
            }

            $log['position'] = [
                'price_history' => [
                    'entry' => $position->price('entry')->history(),
                    'exit'  => $position->price('exit')->history(),
                    'stop'  => $position->price('stop')->history()
                ],
                'trades'        => [
                    $position->getTrades()
                ]
            ];
        }
        else
        {
            $evaluation->entry_timestamp = null;
            $evaluation->highest_entry_price = null;
            $evaluation->lowest_entry_price = null;
            $evaluation->is_ambiguous = null;
            $evaluation->risk_reward_history = null;
            $evaluation->is_entry_price_valid = false;
            $evaluation->is_stopped = null;
            $evaluation->is_closed = null;
            $evaluation->relative_roi = null;
            $evaluation->exit_timestamp = null;
            $evaluation->exit_price = null;
        }

        $evaluation->log = $log;
    }

    /**
     * @param Evaluation $evaluation
     */
    protected function calcHighestLowestPricesToExit(Evaluation $evaluation): void
    {
        $repo = $this->symbolRepo;
        $symbol = $evaluation->entry->symbol;
        $entryTime = $evaluation->entry_timestamp;
        $exitTime = $evaluation->exit->timestamp;

        if (empty($entryTime) || empty($exitTime))
        {
            throw new \LogicException('Entry and exit must be timestamped.');
        }

        if ($entryTime >= $exitTime)
        {
            return;
        }

        $candles = $repo->fetchCandlesBetween($symbol, $entryTime, $exitTime, '1m');
        $lowHigh = $repo->fetchLowestHighestCandle($symbol->id, $candles->first()->t, $candles->last()->t);
        $lowest = $lowHigh['lowest'];
        $highest = $lowHigh['highest'];

        if ($lowest->t > $entryTime)
        {
            $evaluation->lowest_price_to_highest_exit = $repo->fetchLowestHighestCandle($symbol->id,
                $entryTime,
                $highest->t)['lowest']->l;
        }

        if ($highest->t > $entryTime)
        {
            $evaluation->highest_price_to_lowest_exit = $repo->fetchLowestHighestCandle($symbol->id,
                $entryTime,
                $lowest->t)['highest']->h;
        }
    }

    protected function calcHighLowRealRoi(Evaluation $evaluation): void
    {
        if (!$evaluation->is_entry_price_valid || $evaluation->is_ambiguous)
        {
            return;
        }

        $entryPrice = (float)$evaluation->entry_price;
        $buy = $evaluation->entry->side === Signal::BUY;

        $evaluation->highest_roi = Calc::roi($buy, $entryPrice,
            (float)($buy ? $evaluation->highest_price : $evaluation->lowest_price));
        $evaluation->lowest_roi = Calc::roi($buy, $entryPrice,
            (float)(!$buy ? $evaluation->highest_price : $evaluation->lowest_price));
        $evaluation->lowest_to_highest_roi = Calc::roi($buy, $entryPrice,
            (float)($buy ? $evaluation->lowest_price_to_highest_exit : $evaluation->highest_price_to_lowest_exit));

        if (!$exitPrice = $evaluation->exit_price)
        {
            //Realized ROI will be calculated after the exit price
            // is validated in the subsequent evaluations.
            return;
        }

        $evaluation->realized_roi = Calc::roi($buy, $entryPrice, $exitPrice);
    }
}