<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\Summary;
use App\Trade\Calc;
use App\Trade\Strategy\AbstractStrategy;
use Illuminate\Support\Collection;

class Summarizer
{
    protected array $highestRoi = [];
    protected array $lowestRoi = [];

    protected array $profitRoi = [];
    protected array $lossRoi = [];

    protected float $balance = 100;

    protected float $feeRatio;

    protected array $balanceHistory = [];

    protected float $totalFee = 0;

    public function __construct(protected AbstractStrategy $strategy)
    {
        $this->feeRatio = $this->strategy->config('feeRatio');
    }

    public function strategy(): AbstractStrategy
    {
        return $this->strategy;
    }

    /**
     * @param Evaluation[] $evaluations
     *
     * @return array
     */
    public function summarize(Collection $evaluations): Summary
    {
        $summary = $this->setupSummary();
        $evaluations->map(fn(Evaluation $evaluation): Evaluation => $this->processEvaluation($evaluation, $summary));

        $this->calcAverages($summary);

        return $summary;
    }

    protected function recordBalance(int $timestamp)
    {
        if (array_key_exists($timestamp, $this->balanceHistory))
        {
            throw new \LogicException("Balance record for timestamp {$timestamp} already exists.");
        }
        $this->balanceHistory[$timestamp] = $this->balance;
    }

    protected function processEvaluation(Evaluation $evaluation, Summary $summary): Evaluation
    {
        $isEntryValid = (bool)$evaluation->is_entry_price_valid;
        $isAmbiguous = (bool)$evaluation->is_ambiguous;
        $relativeRoi = (float)$evaluation->relative_roi;

        if ($isEntryValid && !$isAmbiguous && $relativeRoi)
        {
            if (!$this->balanceHistory)
            {
                $this->recordBalance($evaluation->entry_timestamp);
            }
            $this->cutCommission($evaluation->used_size, $this->feeRatio * 2);
            $pnl = Calc::pnl($this->balance, $relativeRoi);

            $this->balance += $pnl;
            $this->recordBalance($evaluation->exit_timestamp);
            $summary->total++;

            if ($relativeRoi > 0)
            {
                $this->profitRoi[] = $relativeRoi;
            }
            else if ($relativeRoi < 0)
            {
                $this->lossRoi[] = $relativeRoi;
            }
            $this->highestRoi[] = (float)$evaluation->highest_roi;
            $this->lowestRoi[] = (float)$evaluation->lowest_roi;
        }

        $this->updateCounters($summary, $isAmbiguous, $isEntryValid, $relativeRoi);

        return $evaluation;
    }

    protected function cutCommission(float $usedSize, float|int $ratio): void
    {
        $size = $this->balance * $usedSize / 100;
        $fee = abs($size * $ratio);

        $this->totalFee += $fee;
        $this->balance -= $fee;
    }

    private function updateCounters(Summary $summary, bool $isAmbiguous, bool $isEntryValid, float $roi): void
    {
        if ($isAmbiguous)
        {
            $summary->ambiguous++;
        }
        else if (!$isEntryValid)
        {
            $summary->failed++;
        }
        else if ($roi < 0)
        {
            $summary->loss++;
        }
        else if ($roi > 0)
        {
            $summary->profit++;
        }
    }

    protected function setupSummary(): Summary
    {
        $summary = new Summary();
        $summary->fee_ratio = $this->feeRatio;

        return $summary;
    }

    private function calcAverages(Summary $summary): void
    {
        $summary->roi = round($roi = $this->balance - 100, 2);
        $summary->total_fee = $this->totalFee;
        $summary->balance_history = $this->balanceHistory;

        if ($summary->total > 0)//prevent division by zero
        {
            $summary->avg_roi = round($roi / $summary->total, 2);
            $summary->success_ratio = round($summary->profit / $summary->total * 100, 2);

            if ($this->profitRoi)
            {
                $summary->avg_profit_roi = round(Calc::avg($this->profitRoi), 2);
            }
            if ($this->lossRoi)
            {
                $summary->avg_loss_roi = round(Calc::avg($this->lossRoi), 2);
            }

            if ($this->highestRoi)
            {
                $summary->avg_highest_roi = round(Calc::avg($this->highestRoi), 2);
            }
            if ($this->lowestRoi)
            {
                $summary->avg_lowest_roi = round(Calc::avg($this->lowestRoi), 2);
            }
            if ($this->profitRoi && $this->lossRoi)
            {
                $summary->risk_reward_ratio = round(abs($summary->avg_profit_roi / $summary->avg_loss_roi), 2);
            }
        }
    }
}