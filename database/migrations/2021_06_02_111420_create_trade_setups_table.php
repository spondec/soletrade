<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

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
            $table->foreignId('symbol_id');
            $table->foreignId('signature_id');
            $table->foreignId('position_id')->nullable();
            $table->string('name');
            $table->enum('side', ['BUY', 'SELL']);
            $table->decimal('entry_price');
            $table->decimal('close_price')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->integer('signal_count');
            $table->bigInteger('timestamp');
            $table->string('hash', 32)->unique();
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
