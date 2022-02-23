<?php

namespace Tests\Unit\Trade\Evaluation;

use App\Models\TradeAction;
use App\Models\TradeSetup;
use App\Trade\Action\MoveStop;
use App\Trade\Evaluation\TradeStatus;
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

    public function test_defaults(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $this->assertNotTrue($status->isEntered());
        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->isStopped());
        $this->assertNotTrue($status->isClosed());
        $this->assertNotTrue($status->isAmbiguous());
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
        $this->assertTrue($status->isAmbiguous());
    }

    public function test_close_position_externally(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->isAmbiguous());

        $status->enterPosition(time());
        $status->getPosition()->close(time());

        $this->assertTrue($status->isExited());
        $this->assertTrue($status->isClosed());
        $this->assertNotTrue($status->isStopped());
    }

    public function test_stop_position_externally(): void
    {
        $setup = $this->getSetup(true, 100, 1, 2, 0.5);
        $status = new TradeStatus($setup);

        $this->assertNotTrue($status->isExited());
        $this->assertNotTrue($status->isAmbiguous());
        $status->enterPosition(time());
        $status->getPosition()->stop(time());
        $this->assertTrue($status->isExited());
        $this->assertNotTrue($status->isClosed());
        $this->assertTrue($status->isStopped());
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
