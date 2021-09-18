<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use Illuminate\Support\Collection;

class Summarizer
{
    protected float $balance = 100;

    protected int $total = 0;
    protected int $ambiguous = 0;
    protected int $profit = 0;
    protected int $loss = 0;
    protected int $failed = 0;

    protected array $lowestRois = [];
    protected array $highestRois = [];

    protected float $feeRatio = 0.001;

    /**
     * @param Evaluation[] $evaluations
     *
     * @return array
     */
    public function summarize(Collection $evaluations): array
    {
        foreach ($evaluations as $evaluation)
        {
            $this->processEvaluation($evaluation);
        }

        $stat = $this->getStat();
        $averages = $this->roundArray($this->calcAverages(), 2);

        return array_merge($stat, $averages);
    }

    protected function processEvaluation(Evaluation $evaluation): void
    {
        $validEntry = (bool)$evaluation->is_entry_price_valid;
        $isAmbiguous = (bool)$evaluation->is_ambiguous;
        $realized = (float)$evaluation->realized_roi;

        if ($validEntry && !$isAmbiguous && $realized)
        {
            $this->balance = $this->cutCommission($this->balance, $this->feeRatio * 2);
            $pnl = $this->calculatePnl($this->balance, $realized);
            $this->balance += $pnl;
            $this->total++;

            $this->highestRois[] = (float)$evaluation->highest_roi;
            $this->lowestRois[] = (float)$evaluation->lowest_roi;
        }

        $this->updateCounters($isAmbiguous, $validEntry, $realized);
    }

    public function cutCommission(float|int $balance, float|int $ratio): int|float
    {
        return $balance - abs($balance * $ratio);
    }

    public function calculatePnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
    }

    private function updateCounters(bool $isAmbiguous, bool $validEntry, float $realized): void
    {
        if ($isAmbiguous)
        {
            $this->ambiguous++;
        }
        else if (!$validEntry)
        {
            $this->failed++;
        }
        else if ($realized < 0)
        {
            $this->loss++;
        }
        else if ($realized > 0)
        {
            $this->profit++;
        }
    }

    private function getStat(): array
    {
        return [
            'fee_ratio' => $this->feeRatio,
            'profit'    => $this->profit,
            'loss'      => $this->loss,
            'ambiguous' => $this->ambiguous,
            'failed'    => $this->failed
        ];
    }

    /**
     * @param float[] $items
     */
    protected function roundArray(array $items, int $precision): array
    {
        foreach ($items as &$item)
        {
            $item = round($item, $precision);
        }

        return $items;
    }

    private function calcAverages(): array
    {
        $sum = [
            'avg_roi'           => 0,
            'avg_highest_roi'   => 0,
            'avg_lowest_roi'    => 0,
            'risk_reward_ratio' => 0
        ];

        $sum['roi'] = $roi = $this->balance - 100;

        if ($this->total > 0)//prevent division by zero
        {
            $sum['avg_roi'] = $roi / $this->total;

            if ($this->highestRois)
            {
                $sum['avg_highest_roi'] = $avgHighestRoi = array_sum($this->highestRois) / $this->total;
            }
            if ($this->lowestRois)
            {
                $sum['avg_lowest_roi'] = $avgLowestRoi = array_sum($this->lowestRois) / $this->total;
            }
            if ($this->highestRois && $this->lowestRois)
            {
                $sum['risk_reward_ratio'] = abs($avgHighestRoi / $avgLowestRoi);
            }
        }

        return $sum;
    }
}