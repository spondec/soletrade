<?php

namespace Trade\Evaluation;

use App\Models\Evaluation;
use App\Trade\Evaluation\Summarizer;
use App\Trade\Strategy\AbstractStrategy;
use PHPUnit\Framework\TestCase;

class SummaryTest extends TestCase
{
    protected function getStrategy(): AbstractStrategy
    {
        $strategy = \Mockery::mock(AbstractStrategy::class);
        $strategy->expects('config')
            ->with('feeRatio')
            ->zeroOrMoreTimes()
            ->andReturn(0.001);

        return $strategy;
    }

    public function test_evaluation_count()
    {
        $evaluation = $this->getTenPercentPositiveRoiBuyEvaluation();
        $summarizer = new Summarizer($this->getStrategy());
        $summary = $summarizer->summarize($evaluations = collect([$evaluation]));

        $this->assertEquals($evaluations->count(), $summary->total);
    }

    public function test_fee_ratio()
    {
        $evaluation = $this->getTenPercentPositiveRoiBuyEvaluation();

        $summarizer = new Summarizer($this->getStrategy());
        $summary = $summarizer->summarize(collect([$evaluation]));
        $feeRatio = $summarizer->strategy()->config('feeRatio');

        $this->assertEquals($feeRatio, $summary->fee_ratio);
        $this->assertEquals($this->calcFeeIncludedRoi(10, $feeRatio), $summary->roi);
    }

    protected function calcFeeIncludedRoi(float $roi, float $feeRatio): float
    {
        $balance = (100 - 100 * $feeRatio * 2);
        return $balance + $balance * $roi / 100 - 100;
    }

    protected function getTenPercentPositiveRoiBuyEvaluation(): Evaluation
    {
        /** @var Evaluation $evaluation */
        $evaluation = \Mockery::mock('alias:' . Evaluation::class);
        $evaluation->entry_price = 100;
        $evaluation->stop_price = 50;
        $evaluation->close_price = 200;
        $evaluation->relative_roi = 10;
        $evaluation->highest_price = 110;
        $evaluation->lowest_price = 90;
        $evaluation->lowest_roi = -10;
        $evaluation->highest_roi = 10;
        $evaluation->used_size = 100;
        $evaluation->is_closed = true;
        $evaluation->is_stopped = false;
        $evaluation->is_ambiguous = false;
        $evaluation->is_entry_price_valid = 1;
        $evaluation->entry_timestamp = time() - 86400;
        $evaluation->exit_timestamp = time();
        return $evaluation;
    }
}
