<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTradeSetupSuccessRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_setup_success_rates', function (Blueprint $table) {
            $table->integer('trade_setup_id');
            $table->string('symbol', 50);
            $table->float('success_rate');
            $table->primary(['trade_setup_id', 'symbol']);
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
        Schema::dropIfExists('trade_setup_success_rates');
    }
}
