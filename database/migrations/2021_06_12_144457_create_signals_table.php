<?php

use App\Trade\Illuminate\Database\Schema\Blueprint;
use App\Trade\Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('signals', function (Blueprint $table)
        {
            $table->id();
            $table->foreignId('symbol_id')->constrained();
            $table->foreignId('indicator_id')->constrained('signatures');
            $table->foreignId('signature_id')->constrained();
            $table->string('name', 50)->index();
            $table->enum('side', ['BUY', 'SELL']);
            $table->decimal('price');
            $table->bigInteger('timestamp');
            $table->bigInteger('price_date');
            $table->json('info')->nullable(true);
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
};

