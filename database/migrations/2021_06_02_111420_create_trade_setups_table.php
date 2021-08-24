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
            $table->decimal('price');
            $table->decimal('close_price')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->boolean('valid_price')->default(0);
            $table->integer('signal_count');
            $table->bigInteger('timestamp');

            $table->unique(['symbol_id', 'signature_id', 'name', 'side', 'timestamp']);
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
