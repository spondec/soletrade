<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSignalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_setup_id')->nullable();
            $table->string('type', 50)->index();
            $table->string('indicator', 50);
            $table->integer('indicator_version');
            $table->enum('side',  ['BUY', 'SELL']);
            $table->string('symbol', 50);
            $table->string('interval', 3);
            $table->decimal('price');
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
        Schema::dropIfExists('signals');
    }
}
