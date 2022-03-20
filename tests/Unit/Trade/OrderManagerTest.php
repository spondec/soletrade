<?php

namespace Trade;

use App\Models\Order;
use App\Models\Symbol;
use App\Trade\Exchange\Exchange;
use App\Trade\Exchange\Orderer;
use App\Trade\OrderManager;
use App\Trade\Side;
use App\Trade\TradeAsset;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class OrderManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

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

    protected function getManager(Exchange|MockInterface &$exchange = null,
                                  Symbol|MockInterface   &$symbol = null,
                                  Orderer|MockInterface  &$orderer = null): OrderManager
    {
        $symbol = m::mock('alias:' . Symbol::class);
        $symbol->symbol = 'BTC/USDT';

        $exchange = m::mock(Exchange::class);
        $orderer = m::mock(Orderer::class);

        $exchange
            ->shouldReceive('order')
            ->once()
            ->andReturn($orderer);

        return new OrderManager($exchange, $symbol, m::mock(TradeAsset::class));
    }

    protected function getOrderMock(): MockInterface|Order
    {
        return m::mock(Order::class);
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
                true
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->stopLimit(Side::SELL,
            102,
            100,
            1,
            true));
    }

    public function test_cancel(): void
    {
        /** @var Exchange|MockInterface $exchange */
        /** @var Symbol|MockInterface $symbol */
        /** @var Orderer|MockInterface $orderer */
        $manager = $this->getManager($exchange, $symbol, $orderer);
        $order = $this->getOrderMock();

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
                true
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->market(Side::SELL,
            1,
            true));
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
                true
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->limit(Side::SELL,
            100,
            1,
            true));
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
                true
            ])
            ->andReturn($order);

        $this->assertEquals($order, $manager->stopMarket(Side::SELL,
            1,
            100,
            true));
    }
}
