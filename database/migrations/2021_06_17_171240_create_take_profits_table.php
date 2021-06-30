<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTakeProfitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('take_profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_setup_id');
            $table->float('percent');
            $table->double('price');
            $table->boolean('is_realized')->default(false);
            $table->foreignId('order_id')->nullable()->default(null);
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
        Schema::dropIfExists('take_profits');
    }
}
