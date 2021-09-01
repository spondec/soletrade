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
            $table->enum('side', ['BUY', 'SELL']);
            $table->string('type');
            $table->string('entry_id');
            $table->string('exit_id')->nullable();
            $table->float('realized_roi')->nullable();
            $table->float('highest_roi')->nullable();
            $table->float('lowest_roi')->nullable();
            $table->decimal('highest_price');
            $table->decimal('lowest_price');
            $table->decimal('highest_entry_price')->nullable();
            $table->decimal('lowest_entry_price')->nullable();
            $table->boolean('is_ambiguous')->nullable();
            $table->boolean('is_entry_price_valid')->nullable();
            $table->boolean('is_stopped')->nullable();
            $table->boolean('is_closed')->nullable();
            $table->bigInteger('entry_timestamp')->nullable();
            $table->bigInteger('exit_timestamp')->nullable();
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
