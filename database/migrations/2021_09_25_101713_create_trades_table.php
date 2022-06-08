<?php

use App\Trade\Illuminate\Database\Schema\Blueprint;
use App\Trade\Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->enum('side', \App\Trade\Enum::cases(\App\Trade\Enum\Side::class));
            $table->foreignId('entry_id')->constrained('trade_setups');
            $table->foreignId('exit_id')->nullable()->constrained('trade_setups');
            $table->boolean('is_stopped');
            $table->boolean('is_closed');
            $table->bigInteger('entry_time');
            $table->bigInteger('exit_time')->nullable();
            $table->json('transactions');
            $table->decimal('max_used_size');
            $table->decimal('entry_price');
            $table->decimal('exit_price')->nullable();
            $table->decimal('roi');
            $table->decimal('relative_roi');
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
        Schema::dropIfExists('trades');
    }
};
