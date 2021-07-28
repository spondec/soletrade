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
            $table->id();
            $table->foreignId('symbol_id')->index();
            $table->bigInteger('t')->index();
            $table->decimal('o', 29, 10);
            $table->decimal('c', 29, 10);
            $table->decimal('h', 29, 10);
            $table->decimal('l', 29, 10);
            $table->decimal('v', 29, 10);
            $table->unique(['symbol_id', 't']);
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
