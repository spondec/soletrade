<?php

declare(strict_types=1);

namespace App\Trade\Evaluation;

use App\Models\Evaluation;
use App\Trade\HasConfig;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class Summary implements Arrayable
{
    use HasConfig;

    protected array $config = [
        'feeRatio'   => 0.001,
        'riskReward' => [
            'interval' => '1m',
            'total'    => 3
        ]
    ];

    protected float $balance = 100;

    protected int $total = 0;
    protected int $ambiguous = 0;
    protected int $profit = 0;
    protected int $loss = 0;
    protected int $failed = 0;

    protected array $lowestRoi = [];
    protected array $highestRoi = [];

    protected array $riskReward = [];

    protected array $result;

    public function __construct(protected Collection $evaluations, array $config = [])
    {
        $this->mergeConfig($config);
        $this->result = $this->summarize($evaluations);
    }

    /**
     * @param Evaluation[] $evaluations
     *
     * @return array
     */
    protected function summarize(Collection $evaluations): array
    {
        $evaluations->map(fn(Evaluation $evaluation): Evaluation => $this->processEvaluation($evaluation));

        $stats = $this->getStats();
        $averages = $this->roundArray($this->calcAverages(), 2);

        return array_merge($stats, $averages);
    }

    protected function processEvaluation(Evaluation $evaluation): Evaluation
    {
        $validEntry = (bool)$evaluation->is_entry_price_valid;
        $isAmbiguous = (bool)$evaluation->is_ambiguous;
        $realized = (float)$evaluation->realized_roi;

        if ($validEntry && !$isAmbiguous && $realized)
        {
            $this->balance = $this->cutCommission($this->balance, $this->config['feeRatio'] * 2);
            $pnl = $this->calcPnl($this->balance, $realized);
            $this->balance += $pnl;
            $this->total++;

            $this->highestRoi[] = (float)$evaluation->highest_roi;
            $this->lowestRoi[] = (float)$evaluation->lowest_roi;
        }

        $this->updateCounters($isAmbiguous, $validEntry, $realized);

        return $evaluation;
    }

    public function cutCommission(float|int $balance, float|int $ratio): int|float
    {
        return $balance - abs($balance * $ratio);
    }

    public function calcPnl(float $balance, float $roi): float|int
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

    private function getStats(): array
    {
        return [
            'fee_ratio' => $this->config['feeRatio'],
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
        return array_map(fn(float $v): float => round($v, $precision), $items);
    }

    private function calcAverages(): array
    {
        $sum = [
            'avg_roi'           => 0,
            'avg_highest_roi'   => 0,
            'avg_lowest_roi'    => 0,
            'risk_reward_ratio' => 0,
            'success_ratio'     => 0
        ];

        $sum['roi'] = $roi = $this->balance - 100;

        if ($this->total > 0)//prevent division by zero
        {
            $sum['avg_roi'] = $roi / $this->total;
            $sum['success_ratio'] = $this->profit / $this->total * 100;

            if ($this->highestRoi)
            {
                $sum['avg_highest_roi'] = $avgHighestRoi = array_sum($this->highestRoi) / $this->total;
            }
            if ($this->lowestRoi)
            {
                $sum['avg_lowest_roi'] = $avgLowestRoi = array_sum($this->lowestRoi) / $this->total;
            }
            if ($this->highestRoi && $this->lowestRoi)
            {
                $sum['risk_reward_ratio'] = abs($avgHighestRoi / $avgLowestRoi);
            }
        }

        return $sum;
    }

    public function evaluations(?\Closure $modify = null): Collection
    {
        if ($modify)
        {
            $this->evaluations = $modify($this->evaluations);
        }

        return $this->evaluations;
    }

    public function toArray()
    {
        return [
            'summary'     => $this->result,
            'evaluations' => $this->evaluations->toArray()
        ];
    }
}