<?php

use Illuminate\Database\Migrations\Migration;
use App\Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

class CreateBindingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bindings', function (Blueprint $table) {
            $table->id();
            $table->string('bindable_type');
            $table->foreignId('bindable_id');
            $table->foreignId('signature_id')->constrained();
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
}
