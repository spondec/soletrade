<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCandlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('candles', function (Blueprint $table) {
            $table->string('symbol', 50);
            $table->string('interval', 3);
            $table->enum('exchange', ['BINANCE', 'FTX']);
            $table->integer('start_date', false, true);
            $table->integer('end_date', false, true);
            $table->json('data');
            $table->json('map');
            $table->integer('length');
            $table->primary(['exchange', 'symbol', 'interval', 'start_date']);
            $table->timestamps();

            $table->index('interval');
            $table->index('start_date');
            $table->index('end_date');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('candles');
    }
}
