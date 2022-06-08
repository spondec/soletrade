<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\Summary;
use App\Trade\Calc;
use App\Trade\Strategy\Strategy;
use Illuminate\Support\Collection;

class Summarizer
{
    protected readonly Summary $summary;

    protected float $balance = 100;
    protected array $balanceHistory = [];

    protected float $feeRatio;
    protected float $totalFee = 0;

    protected array $highestRoi = [];
    protected array $lowestRoi = [];
    protected array $profitRoi = [];
    protected array $lossRoi = [];

    public function __construct(protected Strategy $strategy)
    {
        $this->feeRatio = $this->strategy->config('feeRatio');
        $this->summary = $this->setupSummary();
    }

    protected function setupSummary(): Summary
    {
        $summary = new Summary();
        $summary->fee_ratio = $this->feeRatio;

        return $summary;
    }

    public function strategy(): Strategy
    {
        return $this->strategy;
    }

    /** @param Evaluation[] $evaluations */
    public function summarize(Collection $evaluations): Summary
    {
        foreach ($evaluations as $evaluation) {
            $this->addEvaluation($evaluation);
        }

        return $this->getSummary();
    }

    public function addEvaluation(Evaluation $evaluation): void
    {
        $isEntryValid = (bool) $evaluation->is_entry_price_valid;
        $isAmbiguous = (bool) $evaluation->is_ambiguous;
        $relativeRoi = (float) $evaluation->relative_roi;

        if ($isEntryValid && !$isAmbiguous && $relativeRoi) {
            if (!$this->balanceHistory) {
                $this->recordBalance($evaluation->entry_timestamp); //registers initial balance
            }
            $this->cutCommission($evaluation->used_size, $this->feeRatio * 2);
            $pnl = Calc::pnl($this->balance, $relativeRoi);

            $this->balance += $pnl;
            $this->recordBalance($evaluation->exit_timestamp);
            $this->summary->total++;

            if ($relativeRoi > 0) {
                $this->profitRoi[] = $relativeRoi;
            } elseif ($relativeRoi < 0) {
                $this->lossRoi[] = $relativeRoi;
            }
            $this->highestRoi[] = (float) $evaluation->highest_roi;
            $this->lowestRoi[] = (float) $evaluation->lowest_roi;
        }

        $this->updateCounters($isAmbiguous, $isEntryValid, $relativeRoi);
    }

    protected function recordBalance(int $timestamp): void
    {
        if (\array_key_exists($timestamp, $this->balanceHistory)) {
            throw new \LogicException("Balance record for timestamp $timestamp already exists.");
        }

        if (\array_key_last($this->balanceHistory) > $timestamp) {
            throw new \LogicException("New balance record can't be older than previous record.");
        }

        $this->balanceHistory[$timestamp] = $this->balance;
    }

    protected function cutCommission(float $usedSize, float|int $ratio): void
    {
        $size = $this->balance * $usedSize / 100;
        $fee = \abs($size * $ratio);

        $this->totalFee += $fee;
        $this->balance -= $fee;
    }

    protected function updateCounters(bool $isAmbiguous, bool $isEntryValid, float $roi): void
    {
        if ($isAmbiguous) {
            $this->summary->ambiguous++;
        } elseif (!$isEntryValid) {
            $this->summary->failed++;
        } elseif ($roi < 0) {
            $this->summary->loss++;
        } elseif ($roi > 0) {
            $this->summary->profit++;
        }
    }

    public function getSummary(): Summary
    {
        $this->calcAverages();

        return $this->summary;
    }

    protected function calcAverages(): void
    {
        $summary = $this->summary;

        if ($summary->total == 0) {
            return;
        }

        $summary->roi = \round($roi = $this->balance - 100, 2);
        $summary->total_fee = $this->totalFee;
        $summary->balance_history = $this->balanceHistory;
        $summary->avg_roi = \round($roi / $summary->total, 2);
        $summary->success_ratio = \round($summary->profit / $summary->total * 100, 2);

        if ($this->profitRoi) {
            $summary->avg_profit_roi = \round(Calc::avg($this->profitRoi), 2);
        }
        if ($this->lossRoi) {
            $summary->avg_loss_roi = \round(Calc::avg($this->lossRoi), 2);
        }

        if ($this->highestRoi) {
            $summary->avg_highest_roi = \round(Calc::avg($this->highestRoi), 2);
        }
        if ($this->lowestRoi) {
            $summary->avg_lowest_roi = \round(Calc::avg($this->lowestRoi), 2);
        }
        if ($this->profitRoi && $this->lossRoi) {
            $summary->risk_reward_ratio = $summary->avg_loss_roi
                ? \round(\abs($summary->avg_profit_roi / $summary->avg_loss_roi), 2)
                : 0;
        }
    }
}
