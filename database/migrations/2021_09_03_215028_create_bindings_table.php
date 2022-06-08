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
        Schema::create('bindings', function (Blueprint $table)
        {
            $table->id();
            $table->string('bindable_type');
            $table->foreignId('bindable_id');
            $table->string('column');
            $table->string('class');
            $table->string('name');
            $table->unique(['bindable_type', 'bindable_id', 'column']);
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
        Schema::dropIfExists('bindings');
    }
};
