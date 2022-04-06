<?php

namespace Models;

use App\Models\Exchange;
use App\Models\Fill;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_fill(): void
    {
        $order = $this->makeOrder();
        $order->save();
        $fill = $this->makeFills($order, 1)->first();

        $order->onFill(fn() => $this->assertTrue(true));
        $fill->save();
        $this->assertEquals(1, $this->getCount());
    }

    protected function makeOrder(): Order
    {
        $exchange = Exchange::factory(1)
            ->createOne();

        return Order::factory(1)
            ->for($exchange)
            ->makeOne();
    }

    /**
     * @param Order $order
     * @param int   $count
     *
     * @return Fill[]
     */
    protected function makeFills(Order $order, int $count): Collection
    {
        if (!$order->exists)
        {
            throw new \LogicException('Order must be saved before filling.');
        }

        return Fill::factory($count)
            ->for($order)
            ->make();
    }

    public function test_is_all_filled(): void
    {
        $order = $this->makeOrder();
        $order->save();
        $fills = $this->makeFills($order, 3);

        for ($i = 0; $i < 3; $i++)
        {
            $fills[$i]->quantity = $i + 1;
            $fills[$i]->save();
        }

        $order->quantity = 6;
        $order->filled = 0;

        $order->save();

        $this->assertTrue($order->isAllFilled());
    }

    public function test_avg_fill_price(): void
    {
        $order = $this->makeOrder();
        $order->save();
        $fills = $this->makeFills($order, 3);

        for ($i = 0; $i < 3; $i++)
        {
            $fills[$i]->quantity = $i + 1;
            $fills[$i]->price = $i + 1;
            $fills[$i]->save();
        }

        $this->assertEquals(2.33333333333, $order->avgFillPrice());
    }

    public function test_raw_fills(): void
    {
        $order = $this->makeOrder();
        $order->save();

        $model = $this->makeFills($order, 3)
            ->each(fn(Fill $fill) => $fill->save())
            ->only(['id']);

        $db = $order->rawFills()
            ->get()
            ->only('id');

        $this->assertEquals($model->all(), $db->all());
    }

    public function test_unsaved_order_raw_fills_throws_exception()
    {
        $order = $this->makeOrder();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Order is not saved.');
        $order->rawFills();
    }

    public function test_log_response(): void
    {
        $order = $this->makeOrder();
        $order->logResponse('test', $data = ['Response' => 'data']);

        $responses = $order->responses;

        $order->save();
        $this->assertCount(1, end($responses));
        $this->assertEquals($data, end($responses)[0]);
    }

    public function test_fills(): void
    {
        $order = $this->makeOrder();
        $order->save();
        $fills = $this->makeFills($order, 3)
            ->each(fn(Fill $fill) => $fill->save())
            ->only(['id']);

        $this->assertEquals($fills->all(), $order->fills()->get()->only(['id'])->all());
    }
}
