<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

class CreateSignalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('symbol_id');
            $table->foreignId('indicator_id');
            $table->integer('signature_id');
            $table->string('name', 50)->index();
            $table->enum('side', ['BUY', 'SELL']);
            $table->decimal('price');
            $table->bigInteger('timestamp');
            $table->boolean('confirmed')->default(false);
            $table->unique(['symbol_id', 'indicator_id', 'signature_id', 'timestamp']);
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
        Schema::dropIfExists('signals');
    }
}

