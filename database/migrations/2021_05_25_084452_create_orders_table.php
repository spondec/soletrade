<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_setup_id')->nullable();
            $table->boolean('is_open')->default(true);
            $table->enum('status', [
                'CLOSED',
                'OPEN',
                'EXPIRED',
                'NEW',
                'PENDING_CANCEL',
                'REJECTED',
                'CANCELED',
                'PARTIALLY_FILLED']);
            $table->enum('exchange', ['BINANCE', 'FTX']);
            $table->enum('account', ['SPOT', 'FUTURES']);
            $table->string('symbol', 50);
            $table->enum('side', ['BUY', 'SELL', 'LONG', 'SHORT']);
            $table->enum('type', [
                'LIMIT',
                'MARKET',
                'STOP_LOSS',
                'STOP_LOSS_LIMIT',
                'TAKE_PROFIT',
                'TAKE_PROFIT_LIMIT',
                'LIMIT_MAKER']);
            $table->decimal('quantity', 20, 10);
            $table->decimal('filled', 20, 10)->default(0);
            $table->decimal('price', 20, 10)->nullable();
            $table->decimal('stop_price', 20, 10)->nullable();
            $table->decimal('commission', 20, 10)->nullable();
            $table->decimal('commission_asset', 20, 10)->nullable();
            $table->string('exchange_order_id', 255)->nullable()->index();
            $table->json('responses')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
