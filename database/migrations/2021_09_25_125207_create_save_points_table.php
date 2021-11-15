<?php

use Illuminate\Database\Migrations\Migration;
use App\Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;

class CreateSavePointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('save_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('binding_signature_id')->constrained('signatures');
            $table->bigInteger('timestamp');
            $table->decimal('value');
            $table->unique(['binding_signature_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('save_points');
    }
}
