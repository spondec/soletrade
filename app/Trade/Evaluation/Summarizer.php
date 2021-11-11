<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Models\Summary;
use App\Trade\Calc;
use App\Trade\HasConfig;
use Illuminate\Support\Collection;

class Summarizer
{
    use HasConfig;

    protected array $config = [
        'feeRatio'   => 0.001,
        'riskReward' => [
            'interval' => '1m',
            'total'    => 15
        ]
    ];

    protected array $highestRoi;
    protected array $lowestRoi;

    protected float $balance = 100;

    public function __construct(array $config = [])
    {
        $this->mergeConfig($config);
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

    protected function processEvaluation(Evaluation $evaluation, Summary $summary): Evaluation
    {
        $isEntryValid = (bool)$evaluation->is_entry_price_valid;
        $isAmbiguous = (bool)$evaluation->is_ambiguous;
        $relativeRoi = (float)$evaluation->relative_roi;

        if ($isEntryValid && !$isAmbiguous && $relativeRoi)
        {
            $this->balance = $this->cutCommission($this->balance, $evaluation->used_size, $this->config['feeRatio'] * 2);
            $pnl = Calc::pnl($this->balance, $relativeRoi);

            $this->balance += $pnl;
            $summary->total++;

            $this->highestRoi[] = (float)$evaluation->highest_roi;
            $this->lowestRoi[] = (float)$evaluation->lowest_roi;
        }

        $this->updateCounters($summary, $isAmbiguous, $isEntryValid, $relativeRoi);

        return $evaluation;
    }

    public function cutCommission(float|int $balance, float $usedSize, float|int $ratio): int|float
    {
        $size = $balance * $usedSize / 100;
        return $balance - abs($size * $ratio);
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
        $summary->fee_ratio = $this->config['feeRatio'];

        return $summary;
    }

    private function calcAverages(Summary $summary): void
    {
        $summary->roi = round($roi = $this->balance - 100, 2);

        if ($summary->total > 0)//prevent division by zero
        {
            $summary->avg_roi = round($roi / $summary->total, 2);
            $summary->success_ratio = round($summary->profit / $summary->total * 100, 2);

            if ($this->highestRoi)
            {
                $summary->avg_highest_roi = round($avgHighestRoi = array_sum($this->highestRoi) / $summary->total, 2);
            }
            if ($this->lowestRoi)
            {
                $summary->avg_lowest_roi = round($avgLowestRoi = array_sum($this->lowestRoi) / $summary->total, 2);
            }
            if ($this->highestRoi && $this->lowestRoi)
            {
                $summary->risk_reward_ratio = round(abs($avgHighestRoi / $avgLowestRoi), 2);
            }
        }
    }
}