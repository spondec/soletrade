<?php

namespace Trade\Strategy\TradeAction;

use App\Models\TradeAction;
use App\Trade\Action\MoveStop;
use App\Trade\Evaluation\Position;
use App\Trade\Evaluation\Price;
use PHPUnit\Framework\TestCase;

class MoveStopTest extends TestCase
{
    public function test_buy_with_target_roi_50_and_close_price_above_new_stop_price_should_leave_position_open_and_move_stop_to_entry(): void
    {
        $position = new Position(true,
                                 100,
                                 time(),
                                 new Price($entry = 1, time()),
                                 new Price(2, time()),
                                 new Price(0.5, time()));

        $moveStop = $this->newMoveStop($position, [
            'target'         => ['roi' => 50],
            'new_stop_price' => $entry
        ]);

        $candle = [
            'h' => 2,
            'l' => 1,
            'o' => 1.1,
            'c' => 1.5,
            't' => time(),
        ];

        $this->assertEquals(0.5, $position->price('stop')->get());

        $moveStop->run((object)$candle, $candle['t']);

        $this->assertTrue($position->isOpen(), 'Position is expected to be open but is closed.');
        $this->assertEquals(1, $position->price('stop')->get());
    }

    public function test_sell_with_target_roi_50_and_close_price_below_new_stop_price_should_leave_position_open_and_move_stop_to_entry(): void
    {
        $position = new Position(false,
                                 100,
                                 time(),
                                 new Price($entry = 2, time()),
                                 new Price(1, time()),
                                 new Price(3, time()));

        $moveStop = $this->newMoveStop($position, [
            'target'         => ['roi' => 50],
            'new_stop_price' => $entry
        ]);

        $candle = [
            'h' => 2,
            'l' => 1,
            'o' => 1.1,
            'c' => 1.5,
            't' => time(),
        ];

        $this->assertEquals(3, $position->price('stop')->get());

        $moveStop->run((object)$candle, $candle['t']);

        $this->assertTrue($position->isOpen(), 'Position is expected to be open but is closed.');
        $this->assertEquals(2, $position->price('stop')->get());
    }

    public function test_buy_with_target_roi_50_and_close_price_below_new_stop_price_should_close_position_at_close_price(): void
    {
        $position = new Position(true,
                                 100,
                                 time(),
                                 new Price($entry = 1, time()),
                                 new Price(2, time()),
                                 new Price(0.5, time()));

        $moveStop = $this->newMoveStop($position, [
            'target'         => ['roi' => 50],
            'new_stop_price' => $entry
        ]);

        $candle = [
            'h' => 2,
            'l' => 1,
            'o' => 1.1,
            'c' => 0.9,
            't' => time(),
        ];

        $this->assertEquals(0.5, $position->price('stop')->get());

        $moveStop->run((object)$candle, $candle['t']);

        $this->assertNotTrue($position->isOpen(), 'Position is expected to be closed but is open.');
        $this->assertEquals(0.9, $position->price('stop')->get());
    }

    public function test_sell_with_target_roi_50_and_close_price_above_new_stop_price_should_close_position_at_close_price(): void
    {
        $position = new Position(false,
                                 100,
                                 time(),
                                 new Price($entry = 2, time()),
                                 new Price(1, time()),
                                 new Price(3, time()));

        $moveStop = $this->newMoveStop($position, [
            'target'         => ['roi' => 50],
            'new_stop_price' => $entry
        ]);

        $candle = [
            'h' => 3,
            'l' => 1,
            'o' => 1.1,
            'c' => 2.1,
            't' => time(),
        ];

        $this->assertEquals(3, $position->price('stop')->get());

        $moveStop->run((object)$candle, $candle['t']);

        $this->assertNotTrue($position->isOpen(), 'Position is expected to be closed but is open.');
        $this->assertEquals(2.1, $position->price('stop')->get());
    }

    protected function newMoveStop(Position $position, array $config): MoveStop
    {
        $action = new TradeAction();
        $action->config = $config;

        return new MoveStop($position, $action);
    }
}
