<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTradeSetupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->nullable();
            $table->string('name', 30);
            $table->string('symbol', 10);
            $table->enum('side', ['BUY', 'SELL']);
            $table->decimal('entry_price');
            $table->decimal('close_price')->nullable();
            $table->decimal('stop_price');
            $table->json('take_profits')->nullable();
            $table->float('potential_rrr');
            $table->float('realized_rrr')->nullable();
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
        Schema::dropIfExists('trade_setups');
    }
}
