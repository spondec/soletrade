<?php

namespace Trade\Strategy\TradeAction;

use App\Models\TradeAction;
use App\Trade\Evaluation\Position;
use App\Trade\Evaluation\Price;
use App\Trade\Strategy\TradeAction\MoveStop;
use PHPUnit\Framework\TestCase;

class MoveStopTest extends TestCase
{
    public function test_run_with_config_target_roi_with_close_price_above_new_stop_price()
    {
        $position = new Position(true,
            100,
            time(),
            new Price(1),
            new Price(2),
            new Price(0.5));

        $moveStop = $this->newMoveStop($position, [
            'target'         => ['roi' => 50],
            'new_stop_price' => 1
        ]);

        $candle = [
            'h' => 2,
            'l' => 1,
            'o' => 1.1,
            'c' => 1.5,
            't' => time(),
        ];

        $this->assertEquals(0.5, $position->price('stop')->get());

        $moveStop->run((object)$candle);

        $this->assertTrue($position->isOpen(), 'Position is expected to be open but is closed.');
        $this->assertEquals(1, $position->price('stop')->get());
    }

    public function test_run_with_config_target_roi_with_close_price_below_new_stop_price()
    {
        $position = new Position(true,
            100,
            time(),
            new Price(1),
            new Price(2),
            new Price(0.5));

        $moveStop = $this->newMoveStop($position, [
            'target'         => ['roi' => 50],
            'new_stop_price' => 1
        ]);

        $candle = [
            'h' => 2,
            'l' => 1,
            'o' => 1.1,
            'c' => 0.9,
            't' => time(),
        ];

        $this->assertEquals(0.5, $position->price('stop')->get());

        $moveStop->run((object)$candle);

        $this->assertTrue(!$position->isOpen(), 'Position is expected to be closed but is open.');
        $this->assertEquals(0.9, $position->price('stop')->get());
    }

    protected function newMoveStop(Position $position, array $config): MoveStop
    {
        $action = new TradeAction();
        $action->config = $config;

        return new MoveStop($position, $action);
    }
}
