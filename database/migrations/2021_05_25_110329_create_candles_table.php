<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->string('interval');
            $table->enum('exchange', ['BINANCE', 'FTX']);
            $table->binary('data');
            $table->binary('map');
            $table->primary(['symbol', 'interval']);
            $table->timestamps();

            $table->index('interval');
        });

        \Illuminate\Support\Facades\DB::statement('ALTER TABLE `candles` MODIFY COLUMN `data` LONGBLOB');
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
