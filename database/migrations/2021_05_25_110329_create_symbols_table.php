<?php

use App\Trade\Illuminate\Database\Schema\Blueprint;
use App\Trade\Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('symbols', function (Blueprint $table)
        {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('interval', 3)->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->foreignId('exchange_id');
            $table->bigInteger('last_update', unsigned: true)->default(0);
            $table->unique(['exchange_id', 'symbol', 'interval']);
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
        Schema::dropIfExists('symbols');
    }
};
