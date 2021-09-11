<?php

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use Illuminate\Support\Collection;

class Summarizer
{
    /**
     * @param Evaluation[] $evaluations
     *
     * @return array
     */
    public function summarize(Collection $evaluations): array
    {
        $balance = 100;
        $count = 0;

        $ambiguous = 0;
        $profit = 0;
        $loss = 0;
        $failed = 0;

        $lowestRois = [];
        $highestRois = [];

        foreach ($evaluations as $evaluation)
        {
            $validEntry = $evaluation->is_entry_price_valid;
            $isAmbiguous = $evaluation->is_ambiguous;
            $realized = $evaluation->realized_roi;

            if ($validEntry && !$isAmbiguous && $realized)
            {
                $realizedRois[] = $realized;
                $balance = $this->cutCommission($balance, 0.002);
                $pnl = $this->calculatePnl($balance, $realized);
                $balance += $pnl;
                $count++;

                $highestRois[] = (float)$evaluation->highest_roi;
                $lowestRois[] = (float)$evaluation->lowest_roi;
            }

            if ($isAmbiguous)
            {
                $ambiguous++;
            }
            else if (!$validEntry)
            {
                $failed++;
            }
            else if ($realized < 0)
            {
                $loss++;
            }
            else if ($realized > 0)
            {
                $profit++;
            }
        }

        $sum = [
            'avg_roi'           => 0,
            'avg_highest_roi'   => 0,
            'avg_lowest_roi'    => 0,
            'risk_reward_ratio' => 0,
            'profit'            => $profit,
            'loss'              => $loss,
            'ambiguous'         => $ambiguous,
            'failed'            => $failed
        ];

        $sum['roi'] = $roi = round($balance - 100, 2);

        if ($count > 0)//prevent division by zero
        {
            $sum['avg_roi'] = round($roi / $count, 2);

            if ($highestRois)
            {
                $sum['avg_highest_roi'] = round($avgHighestRoi = array_sum($highestRois) / $count, 2);
            }
            if ($lowestRois)
            {
                $sum['avg_lowest_roi'] = round($avgLowestRoi = array_sum($lowestRois) / $count, 2);
            }
            if ($highestRois && $lowestRois)
            {
                $sum['risk_reward_ratio'] = round(abs($avgHighestRoi / $avgLowestRoi), 2);
            }
        }

        return $sum;
    }

    public function cutCommission(float|int $balance, float|int $ratio): int|float
    {
        return $balance - abs($balance * $ratio);
    }

    public function calculatePnl(float $balance, float $roi): float|int
    {
        return $balance * $roi / 100;
    }
}