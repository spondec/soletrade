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
            $table->enum('exchange',  ['BINANCE', 'FTX']);
            $table->enum('account', ['SPOT', 'FUTURES']);
            $table->string('symbol', 50);
            $table->enum('side', ['BUY', 'SELL']);
            $table->enum('type',
                         [
                             'LIMIT',
                             'MARKET',
                             'STOP_LOSS',
                             'STOP_LOSS_LIMIT',
                             'TAKE_PROFIT',
                             'TAKE_PROFIT_LIMIT',
                             'LIMIT_MAKER'
                         ]);
            $table->decimal('quantity');
            $table->decimal('filled');
            $table->decimal('price');
            $table->decimal('stop_price')->nullable();
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
