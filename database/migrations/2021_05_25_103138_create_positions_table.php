<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePositionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_open')->default(true);
            $table->foreignId('exchange_id');
            $table->string('symbol', 20);
            $table->enum('side', ['BUY', 'SELL']);
            $table->decimal('quantity');
            $table->string('quantity_type', 10);
            $table->decimal('entry_price');
            $table->decimal('avg_price');
            $table->decimal('liq_price');
            $table->decimal('margin')->nullable();
            $table->decimal('pnl')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->decimal('take_profit_price')->nullable();
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
        Schema::dropIfExists('positions');
    }
}
