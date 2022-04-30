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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->enum('side', \App\Trade\Enum::cases(\App\Trade\Side::class));
            $table->foreignId('entry_id')->constrained('trade_setups');
            $table->foreignId('exit_id')->nullable()->constrained('trade_setups');
            $table->boolean('is_stopped');
            $table->boolean('is_closed');
            $table->bigInteger('entry_time');
            $table->bigInteger('exit_time')->nullable();
            $table->json('transactions');
            $table->float('max_used_size');
            $table->decimal('entry_price');
            $table->decimal('exit_price')->nullable();
            $table->float('roi');
            $table->float('relative_roi');
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
        Schema::dropIfExists('positions');
    }
};
