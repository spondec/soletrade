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
        Schema::create('signatures', function (Blueprint $table)
        {
            $table->id();
            $table->json('data');
            $table->string('hash', 32)->unique();
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
        Schema::dropIfExists('signatures');
    }
};
