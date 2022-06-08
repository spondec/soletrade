<?php

namespace Tests\Unit\Trade\Exchange;

use App\Models\Order;
use App\Models\Symbol;
use App\Models\TradeSetup;
use App\Trade\Enum\Side;
use App\Trade\Exchange\Account\TradeAsset;
use App\Trade\Exchange\Exchange;
use App\Trade\Exchange\Orderer;
use App\Trade\Exchange\OrderManager;
use Mockery as m;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class OrderManagerTest extends m\Adapter\Phpunit\MockeryTestCase
{
    public function test_sync(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

        $orderer
            ->shouldReceive('sync')
            ->with($order)
            ->once()
            ->andReturn([]);

        $this->assertEquals([], $manager->sync($order));
    }

    protected function getManager(
        Exchange|MockInterface &$exchange = null,
        Symbol|MockInterface &$symbol = null,
        Orderer|MockInterface &$orderer = null
    ): OrderManager
    {
        $symbol = m::mock('alias:' . Symbol::class);
        $symbol->symbol = 'BTC/USDT';

        $exchange = m::mock(Exchange::class);
        $orderer = m::mock(Orderer::class);

        $exchange
            ->shouldReceive('order')
            ->once()
            ->andReturn($orderer);

        return new OrderManager($exchange, $symbol, m::mock(TradeAsset::class), m::mock(TradeSetup::class));
    }

    protected function getOrderMock(?\Closure &$cancelListener = null): MockInterface|Order
    {
        static $id = 0;
        $order = m::mock('alias:' . Order::class);
        $order->id = ++$id;

        $order->shouldReceive('onCancel')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (\Closure $callback) use (&$cancelListener)
            {
                $cancelListener = $callback;
            });
        $order->shouldReceive('flushListeners');
        $order->shouldReceive('isOpen')->zeroOrMoreTimes()->andReturn(true);

        return $order;
    }

    public function test_stop_limit(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

        $orderer
            ->shouldReceive('stopLimit')
            ->once()
            ->withArgs([
                Side::SELL,
                $symbol->symbol,
                102,
                100,
                1,
                true,
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->stopLimit(
            Side::SELL,
            102,
            100,
            1,
            true
        ));
    }

    public function test_cancel(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

        $exchange->shouldReceive('order')
            ->zeroOrMoreTimes()
            ->andReturn($orderer);

        $orderer
            ->shouldReceive('sync')
            ->once()
            ->with($order)
            ->andReturn([]);

        $orderer
            ->shouldReceive('cancel')
            ->once()
            ->with($order)
            ->andReturn($order);

        $this->assertEquals($order, $manager->cancel($order));
    }

    public function test_market(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

        $orderer
            ->shouldReceive('market')
            ->once()
            ->withArgs([
                Side::SELL,
                $symbol->symbol,
                1,
                true,
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->market(
            Side::SELL,
            1,
            true
        ));
    }

    public function test_limit(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

        $orderer
            ->shouldReceive('limit')
            ->once()
            ->withArgs([
                Side::SELL,
                $symbol->symbol,
                100,
                1,
                true,
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->limit(
            Side::SELL,
            100,
            1,
            true
        ));
    }

    public function test_stop_market(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

        $orderer
            ->shouldReceive('stopMarket')
            ->once()
            ->withArgs([
                Side::SELL,
                $symbol->symbol,
                1,
                100,
                true,
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->stopMarket(
            Side::SELL,
            1,
            100,
            true
        ));
    }

    public function test_order_cancel_listener()
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock($cancelListener);

        $orderer
            ->shouldReceive('market')
            ->once()
            ->withArgs([
                Side::SELL,
                $symbol->symbol,
                1,
                true,
            ])
            ->andReturn($order);

        $manager->market(Side::SELL, 1, true);

        $manager->entry = $order;
        $manager->exit = $order;
        $manager->stop = $order;

        $cancelListener($order);

        $this->assertEquals(null, $manager->entry);
        $this->assertEquals(null, $manager->exit);
        $this->assertEquals(null, $manager->stop);
    }
}
