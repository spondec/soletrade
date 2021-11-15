<?php

use Illuminate\Database\Migrations\Migration;
use App\Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

class CreateTradeActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_setup_id')->constrained();
            $table->string('class');
            $table->json('config');
            $table->boolean('is_taken')->default(false);
            $table->bigInteger('timestamp')->nullable(true);
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
        Schema::dropIfExists('trade_actions');
    }
}
