<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->constrained();
            $table->foreignId('signature_id')->constrained();
            $table->string('name');
            $table->enum('side', ['BUY', 'SELL']);
            $table->float('size');
            $table->bigInteger('timestamp');
            $table->bigInteger('price_date');
            $table->decimal('price')->nullable();
            $table->decimal('target_price')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->integer('signal_count');
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
};
