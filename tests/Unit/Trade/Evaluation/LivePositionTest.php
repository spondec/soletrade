<?php

namespace Tests\Unit\Trade\Evaluation;

use App\Models\Fill;
use App\Models\Order;
use App\Trade\Enum\OrderType;
use App\Trade\Enum\Side;
use App\Trade\Evaluation\LivePosition;
use App\Trade\Evaluation\Price;
use App\Trade\Exchange\Account\TradeAsset;
use App\Trade\Exchange\OrderManager;
use App\Trade\Order\Type\Handler;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LivePositionTest extends TestCase
{
    public function test_process_entry_order_fill(): void
    {
        /** @var MockInterface|OrderManager $manager */
        $pos = $this->getPosition($manager, 50);

        $fill = $this->getFill(50 / 100, 100);

        $pos->processEntryOrderFill($fill);

        $this->assertEquals(1, $pos->getOwnedQuantity());

        $this->assertTrue($pos->isOpen());
        $this->assertEquals(100, $pos->getUsedSize());
        $this->assertEquals(1, $pos->getAssetAmount());

        $log = $pos->transactionLog();
        $last = $log->last()['value'];

        $this->assertCount(2, $log->get());
        $this->assertEquals(50, $last['size']);
        $this->assertEquals(100, $last['price']);
    }

    protected function getPosition(?OrderManager &$manager = null, float $size = 100): LivePosition
    {
        $manager = m::mock('alias:' . OrderManager::class);
        $manager->tradeAsset = m::mock(TradeAsset::class);

        $manager->stop = null;
        $manager->entry = null;
        $manager->exit = null;

        $manager->tradeAsset
            ->shouldReceive('proportional')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (float $size) {
                return $size;
            });

        $pos = new LivePosition(
            Side::BUY,
            $size,
            time(),
            $entry = new Price(100, time()),
            new Price(102, time()),
            new Price(99, time()),
            $manager,
            $this->getFill($quantity = $size / $entry->get(), $entry->get())
        );

        $this->assertEquals($quantity, $pos->getOwnedQuantity());

        return $pos;
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

        $price = 100;
        $size = 25;

        $quantity = $size / $price;

        $order = $this->getOrder($pos->side->opposite(), $pos->decreaseOrderType, $price, $quantity, true, $fillCallbacks);
        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->decreaseSize($size, $price, time(), 'Decrease');
        $this->assertCount(1, $fillCallbacks);
        $fillCallbacks[0]($fill);

        $this->assertEquals(0.25, $pos->getOwnedQuantity());

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

    public function test_increase_size(): void
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

        $this->assertEquals(0.75, $pos->getOwnedQuantity());

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
        $pos = $this->getPosition($manager, size: $size = 50);

        $price = $pos->price('stop')->get();

        $quantity = 0.5;

        $order = $this->getOrder($pos->side->opposite(),
            $pos->stopOrderType,
            $price,
            $quantity,
            true,
            $fillCallbacks,
            2);

        $order->shouldReceive('isAllFilled')->once()->andReturn(true);
        $order->shouldReceive('avgFillPrice')->once()->andReturn($price);

        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->sendStopOrder();

        $fillCallbacks[0]($fill);
        $fillCallbacks[1]($fill);

        $this->assertEquals(0, $pos->getOwnedQuantity());

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
        $pos = $this->getPosition($manager, size: $size = 50);

        $price = $pos->price('exit')->get();

        $quantity = 0.5;

        $order = $this->getOrder($pos->side->opposite(),
            $pos->exitOrderType,
            $price,
            $quantity,
            true,
            $fillCallbacks,
            2);

        $order->shouldReceive('isAllFilled')->once()->andReturn(true);
        $order->shouldReceive('avgFillPrice')->once()->andReturn($price);

        $fill = $this->getFill($quantity, $price);

        $this->expectOrder($manager, $order);

        $pos->sendExitOrder();

        $fillCallbacks[0]($fill);
        $fillCallbacks[1]($fill);

        $this->assertEquals(0, $pos->getOwnedQuantity());

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
