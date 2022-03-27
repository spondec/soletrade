<?php

namespace Trade\Evaluation;

use App\Models\Fill;
use App\Models\Order;
use App\Models\OrderType;
use App\Trade\Evaluation\LivePosition;
use App\Trade\Evaluation\Price;
use App\Trade\Order\Type\Handler;
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
class LivePositionTest extends TestCase
{
    public function test_quantity(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager);
        /** @var MockInterface $asset */
        $asset = $manager->tradeAsset;

        $price = 42;
        $proportionalSize = 34;

        $asset
            ->shouldReceive('quantity')
            ->once()
            ->withArgs([$price, $proportionalSize])
            ->andReturn(99);

        $this->assertEquals(99, $pos->quantity($price, $proportionalSize));
    }

    protected function getPosition(?OrderManager &$manager = null, float $size = 100): LivePosition
    {
        $manager = m::mock('alias:' . OrderManager::class);
        $manager->tradeAsset = m::mock(TradeAsset::class)->makePartial();

        $manager->stop = null;
        $manager->entry = null;
        $manager->exit = null;

        return new LivePosition(
            Side::BUY,
            $size,
            time(),
            new Price(100, time()),
            new Price(102, time()),
            new Price(99, time()),
            $manager
        );
    }

    public function test_send_increase_order_above_remaining_size_throws_exception(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, size: 50);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requested size is bigger than the remaining');
        $pos->increaseSize(51, 123, time(), 'Increase');
    }

    public function test_send_decrease_order_above_used_size_throws_exception(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, size: 50);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Reduce size can not be greater than used size');
        $pos->decreaseSize(51, 123, time(), 'Decrease');
    }

    public function test_decrease_size(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, size: 50);

        /** @var MockInterface $asset */
        $asset = $manager->tradeAsset;

        $price = 100;
        $size = 25;

        $quantity = $this->expectQuantity($asset, $price, $size);

        $order = $this->getOrder($pos->side->opposite(), $pos->decreaseOrderType, $price, $quantity, true, $fillCallbacks);
        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->decreaseSize($size, $price, time(), 'Decrease');
        $this->assertCount(1, $fillCallbacks);
        $fillCallbacks[0]($fill);

        $log = $pos->transactionLog();

        $this->assertCount(2, $log->get());
        $last = $log->last()['value'];

        $this->assertEquals(false, $last['increase']);
        $this->assertEquals($price, $last['price']);
        $this->assertEquals($size, $last['size']);
    }

    protected function expectQuantity(MockInterface|TradeAsset $asset, int $price, int $size): float
    {
        $asset
            ->shouldReceive('quantity')
            ->once()
            ->withArgs([$price, $size])
            ->andReturn($quantity = $size / $price);

        $asset
            ->shouldReceive('proportional')
            ->once()
            ->with($size)
            ->andReturn($size);

        return $quantity;
    }

    protected function getOrder(Side      $side,
                                OrderType $type,
                                int       $price,
                                float     $quantity,
                                bool      $reduceOnly,
                                ?array    &$fillCallbacks = null,
                                int       $fillCount = 1): MockInterface|Order
    {
        $order = m::mock('alias:' . Order::class);

        $order->side = $side;
        $order->type = $type;
        $order->price = $price;
        $order->quantity = $quantity;
        $order->reduce_only = $reduceOnly;

        $order->shouldReceive('onFill')
            ->times($fillCount)
            ->andReturnUsing(function (\Closure $callback) use (&$fillCallbacks) {
                $fillCallbacks[] = $callback;
            });

        return $order;
    }

    protected function getFill(float $quantity, int $price): MockInterface|Fill
    {
        $fill = m::mock('alias:' . Fill::class);

        $fill->timestamp = time() * 1000;
        $fill->quantity = $quantity;
        $fill->price = $price;

        $fill->shouldReceive('quoteSize')
            ->zeroOrMoreTimes()
            ->andReturn($quantity * $price);

        return $fill;
    }

    protected function expectOrder(MockInterface|OrderManager $manager, Order|MockInterface $order): void
    {
        $manager
            ->shouldReceive('handler')
            ->once()
            ->andReturns($handler = m::mock(Handler::class));

        $handler
            ->shouldReceive('order')
            ->once()
            ->withArgs([$order->type, $order->quantity, $order->price, $order->reduce_only])
            ->andReturns($order);
    }

    public function test_send_increase_order(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, size: 50);

        /** @var MockInterface $asset */
        $asset = $manager->tradeAsset;

        $price = 100;
        $size = 25;

        $quantity = $this->expectQuantity($asset, $price, $size);

        $order = $this->getOrder($pos->side, $pos->increaseOrderType, $price, $quantity, false, $fillCallbacks);
        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->increaseSize($size, $price, time(), 'Increase');

        $this->assertCount(1, $fillCallbacks);
        $fillCallbacks[0]($fill);

        $log = $pos->transactionLog();

        $this->assertCount(2, $log->get());
        $last = $log->last()['value'];

        $this->assertEquals(true, $last['increase']);
        $this->assertEquals($price, $last['price']);
        $this->assertEquals($size, $last['size']);
    }

    public function test_send_stop_order(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, size: 50);

        /** @var MockInterface $asset */
        $asset = $manager->tradeAsset;

        $price = $pos->price('stop')->get();
        $size = 50;

        $quantity = $this->expectQuantity($asset, $price, $size);

        $order = $this->getOrder($pos->side->opposite(), $pos->stopOrderType, $price, $quantity, true, $fillCallbacks, 2);

        $order->shouldReceive('isAllFilled')->once()->andReturn(true);
        $order->shouldReceive('avgFillPrice')->once()->andReturn($price);

        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->sendStopOrder();

        $fillCallbacks[0]($fill);
        $fillCallbacks[1]($fill);

        $this->assertTrue($pos->isStopped());
        $this->assertFalse($pos->isOpen());
        $this->assertFalse($pos->isClosed());

        $log = $pos->transactionLog();
        $this->assertCount(2, $log->get());
        $last = $log->last()['value'];

        $this->assertEquals(false, $last['increase']);
        $this->assertEquals($price, $last['price']);
        $this->assertEquals($size, $last['size']);
    }

    public function test_send_exit_order(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, size: 50);

        /** @var MockInterface $asset */
        $asset = $manager->tradeAsset;

        $price = $pos->price('exit')->get();
        $size = 50;

        $quantity = $this->expectQuantity($asset, $price, $size);

        $order = $this->getOrder($pos->side->opposite(), $pos->exitOrderType, $price, $quantity, true, $fillCallbacks, 2);

        $order->shouldReceive('isAllFilled')->once()->andReturn(true);
        $order->shouldReceive('avgFillPrice')->once()->andReturn($price);

        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->sendExitOrder();

        $fillCallbacks[0]($fill);
        $fillCallbacks[1]($fill);

        $this->assertFalse($pos->isStopped());
        $this->assertFalse($pos->isOpen());
        $this->assertTrue($pos->isClosed());

        $log = $pos->transactionLog();
        $this->assertCount(2, $log->get());
        $last = $log->last()['value'];

        $this->assertEquals(false, $last['increase']);
        $this->assertEquals($price, $last['price']);
        $this->assertEquals($size, $last['size']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }
}
