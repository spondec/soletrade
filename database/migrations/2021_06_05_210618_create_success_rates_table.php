<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuccessRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('success_rates', function (Blueprint $table) {
            $table->integer('trade_setup_id');
            $table->string('symbol', 50);
            $table->string('interval', 3);
            $table->integer('success_rate');
            $table->primary(['trade_setup_id', 'symbol', 'interval']);
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
        Schema::dropIfExists('success_rates');
    }
}
