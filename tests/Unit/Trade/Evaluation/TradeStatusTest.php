<?php

namespace Tests\Unit\Trade\Evaluation;

use App\Models\TradeAction;
use App\Models\TradeSetup;
use App\Trade\Evaluation\TradeStatus;
use App\Trade\Strategy\TradeAction\MoveStop;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class TradeStatusTest extends TestCase
{
    public function test_run_trade_actions(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);

        $action = Mockery::mock('alias:' . TradeAction::class);
        $action->config = [
            'target'         => [
                'roi' => 10
            ],
            'new_stop_price' => 1
        ];
        $action->is_taken = false;
        $action->class = MoveStop::class;
        $action->expects('save')->andReturn(true);

        $setup->actions = new Collection([$action]);

        $status = new TradeStatus($setup);
        $status->enterPosition(time());

        $candle = [
            'h' => 1.10,
            'l' => 0.9,
            'o' => 1,
            'c' => 1.2,
            't' => time() + 1
        ];

        $status->runTradeActions((object)$candle, $candle['t']);
        $this->assertEquals(1, $status->getStopPrice()->get());
    }

    public function test_update_lowest_highest_entry_price(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $candle = [
            'h' => 999,
            'l' => 111
        ];

        $status->updateLowestHighestEntryPrice((object)$candle);
        $this->assertEquals(999, $status->getHighestEntryPrice());
        $this->assertEquals(111, $status->getLowestEntryPrice());

        $candle['h'] = 1001;
        $candle['l'] = 10;

        $status->updateLowestHighestEntryPrice((object)$candle);
        $this->assertEquals(1001, $status->getHighestEntryPrice());
        $this->assertEquals(10, $status->getLowestEntryPrice());
    }

    public function test_update_lowest_highest_price(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $highest = ['h' => 999];
        $lowest = ['l' => 111];

        $status->updateHighestLowestPrice((object)$highest, (object)$lowest);
        $this->assertEquals(999, $status->getHighestPrice());
        $this->assertEquals(111, $status->getLowestPrice());

        $highest['h'] = 1001;
        $lowest['l'] = 10;

        $status->updateHighestLowestPrice((object)$highest, (object)$lowest);
        $this->assertEquals(1001, $status->getHighestPrice());
        $this->assertEquals(10, $status->getLowestPrice());
    }

    public function test_defaults(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $this->assertNotTrue($status->isEntered());
        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->isStopped());
        $this->assertNotTrue($status->isClosed());
        $this->assertNotTrue($status->isAmbiguous());
        $this->assertNotTrue($status->checkIsExited());
    }

    public function test_sell_log_risk_reward_history(): void
    {
        $setup = $this->getSetup(false, 100, 1, 0.5, 1.25);
        $status = new TradeStatus($setup);
        $candle = [
            'h' => 1.25,
            'l' => 0.5,
            't' => time()
        ];
        $status->logRiskReward((object)$candle);

        $candle['l'] = 0.25;
        $candle['h'] = 1.5;
        ++$candle['t'];
        $status->logRiskReward((object)$candle);

        $record = $status->riskRewardHistory()->shift();
        $this->assertEquals(2, $record['ratio']);
        $this->assertEquals(-25, $record['risk']);
        $this->assertEquals(50, $record['reward']);

        $record = $status->riskRewardHistory()->shift();
        $this->assertEquals(75 / 50, $record['ratio']);
        $this->assertEquals(-50, $record['risk']);
        $this->assertEquals(75, $record['reward']);
    }

    public function test_buy_log_risk_reward_history(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);
        $candle = [
            'h' => 2,
            'l' => 0.5,
            'o' => 1.2,
            'c' => 1.5,
            't' => time()
        ];
        $status->logRiskReward((object)$candle);

        $candle['h'] = 3;
        ++$candle['t'];
        $status->logRiskReward((object)$candle);

        $record = $status->riskRewardHistory()->shift();
        $this->assertEquals(2, $record['ratio']);
        $this->assertEquals(-50, $record['risk']);
        $this->assertEquals(100, $record['reward']);

        $record = $status->riskRewardHistory()->shift();
        $this->assertEquals(4, $record['ratio']);
        $this->assertEquals(-50, $record['risk']);
        $this->assertEquals(200, $record['reward']);
    }

    protected function getSetup(bool $isBuy, float $size, float $price, float $closePrice, float $stopPrice): TradeSetup
    {
        $setup = Mockery::mock('alias:' . TradeSetup::class);

        $setup->shouldReceive('isBuy')->andReturn($isBuy);
        $setup->actions = new Collection();
        $setup->size = $size;
        $setup->price = $price;
        $setup->close_price = $closePrice;
        $setup->stop_price = $stopPrice;
        $setup->price_date = time();
        return $setup;
    }

    public function test_enter_position(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $status->enterPosition(time());
        $this->assertTrue($status->isEntered());
        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->isStopped());
        $this->assertNotTrue($status->isClosed());
        $this->assertNotTrue($status->isAmbiguous());
        $this->assertNotTrue($status->checkIsExited());
    }

    public function test_check_is_stopped(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);
        $candle = [
            'h' => 1.9,
            'l' => 0.6,
            't' => time()
        ];
        $status->enterPosition(time());
        $this->assertNotTrue($status->checkIsStopped((object)$candle));
        $candle['l'] = 0.5;
        $this->assertTrue($status->checkIsStopped((object)$candle));
        $this->assertNotTrue($status->isAmbiguous());
    }

    public function test_check_is_ambiguous(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);
        $candle = [
            'h' => 2,
            'l' => 0.5,
            't' => time()
        ];
        $status->enterPosition(time());
        $this->assertTrue($status->checkIsStopped((object)$candle));
        $this->assertTrue($status->checkIsClosed((object)$candle));
        $this->assertTrue($status->isExited());
        $this->assertTrue($status->checkIsExited());
        $this->assertTrue($status->isAmbiguous());
    }

    public function test_close_position_externally(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);
        $candle = [
            'h' => 1.9,
            'l' => 0.7,
            't' => time()
        ];
        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->checkIsExited());
        $this->assertNotTrue($status->isAmbiguous());
        $status->enterPosition(time());
        $status->getPosition()->close(time());
        $this->assertNotTrue($status->isExited());
        $this->assertTrue($status->checkIsExited());
        $this->assertTrue($status->checkIsClosed((object)$candle));
        $this->assertNotTrue($status->checkIsStopped((object)$candle));
        $this->assertTrue($status->isExited());
    }

    public function test_stop_position_externally(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);
        $candle = [
            'h' => 1.9,
            'l' => 0.7,
            't' => time()
        ];
        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->checkIsExited());
        $this->assertNotTrue($status->isAmbiguous());
        $status->enterPosition(time());
        $status->getPosition()->stop(time());
        $this->assertNotTrue($status->isExited());
        $this->assertTrue($status->checkIsExited());
        $this->assertNotTrue($status->checkIsClosed((object)$candle));
        $this->assertTrue($status->checkIsStopped((object)$candle));
        $this->assertTrue($status->isExited());
    }

    public function test_check_is_closed_with_no_position(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $candle = [
            'h' => 1.9,
            'l' => 1,
            't' => time()
        ];
        $this->expectExceptionMessage('Position has not been initialized');
        $this->assertNotTrue($status->checkIsClosed((object)$candle));
    }

    public function test_check_is_stopped_with_no_position(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $candle = [
            'h' => 1.9,
            'l' => 1,
            't' => time()
        ];

        $this->expectExceptionMessage('Position has not been initialized');
        $this->assertNotTrue($status->checkIsStopped((object)$candle));
    }

    public function test_check_is_closed(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $candle = [
            'h' => 1.9,
            'l' => 1,
            't' => time()
        ];

        $status->enterPosition(time());
        $this->assertNotTrue($status->checkIsClosed((object)$candle));
        $candle['h'] = 2;
        $this->assertTrue($status->checkIsClosed((object)$candle));
        $this->assertNotTrue($status->isAmbiguous());
    }
}
