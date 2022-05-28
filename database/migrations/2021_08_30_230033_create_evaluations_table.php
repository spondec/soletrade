<?php

use App\Trade\Illuminate\Database\Schema\Blueprint;
use App\Trade\Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('entry_id')->constrained('trade_setups');
            $table->foreignId('exit_id')->nullable()->constrained('trade_setups');
            $table->foreignId('symbol_id')->constrained('symbols');
            $table->float('relative_roi')->nullable();
            $table->float('highest_roi')->nullable();
            $table->float('lowest_roi')->nullable();
            $table->float('lowest_to_highest_roi')->nullable();
            $table->float('used_size')->default(0);
            $table->decimal('entry_price')->nullable();
            $table->decimal('avg_entry_price')->nullable();
            $table->decimal('exit_price')->nullable();
            $table->decimal('target_price')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->decimal('highest_price')->nullable();
            $table->decimal('lowest_price')->nullable();
            $table->decimal('highest_entry_price')->nullable();
            $table->decimal('lowest_entry_price')->nullable();
            $table->boolean('is_entry_price_valid')->default(0);
            $table->boolean('is_ambiguous')->nullable();
            $table->boolean('is_stopped')->nullable();
            $table->boolean('is_closed')->nullable();
            $table->bigInteger('entry_timestamp')->nullable();
            $table->bigInteger('exit_timestamp')->nullable();
            $table->json('log')->nullable();
            $table->unique(['type', 'entry_id', 'exit_id']);
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
        Schema::dropIfExists('evaluations');
    }
};
