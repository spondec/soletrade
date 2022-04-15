<?php

use Illuminate\Database\Migrations\Migration;
use App\Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id')->index()->constrained();
            $table->bigInteger('t')->index();
            $table->decimal('o');
            $table->decimal('c');
            $table->decimal('h');
            $table->decimal('l');
            $table->decimal('v');
            $table->index(['symbol_id', 'l']);
            $table->index(['symbol_id', 'h']);
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
};