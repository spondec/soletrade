<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

class CreateEvaluationsTable extends Migration
{
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
            $table->foreignId('entry_id');
            $table->foreignId('exit_id')->nullable();
            $table->float('realized_roi')->nullable();
            $table->float('highest_roi')->nullable();
            $table->float('lowest_roi')->nullable();
            $table->decimal('entry_price')->nullable();
            $table->decimal('exit_price')->nullable();
            $table->decimal('close_price')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->decimal('highest_price');
            $table->decimal('lowest_price');
            $table->decimal('highest_price_to_lowest_exit')->nullable();
            $table->decimal('lowest_price_to_highest_exit')->nullable();
            $table->decimal('highest_entry_price')->nullable();
            $table->decimal('lowest_entry_price')->nullable();
            $table->boolean('is_entry_price_valid')->default(0);
            $table->boolean('is_exit_price_valid')->default(0);
            $table->boolean('is_ambiguous')->nullable();
            $table->boolean('is_stopped')->nullable();
            $table->boolean('is_closed')->nullable();
            $table->bigInteger('entry_timestamp')->nullable();
            $table->bigInteger('exit_timestamp')->nullable();
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
}
