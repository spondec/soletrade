<?php

use App\Illuminate\Database\Schema\Blueprint;
use App\Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained();
            $table->foreignId('trade_id')->nullable()->constrained();
            $table->boolean('reduce_only');
            $table->enum('status', \App\Trade\Enum::cases(\App\Trade\Enum\OrderStatus::class));
            $table->string('symbol', 50);
            $table->enum('side', \App\Trade\Enum::cases(\App\Trade\Enum\Side::class));
            $table->enum('type', \App\Trade\Enum::cases(\App\Trade\Enum\OrderType::class));
            $table->decimal('quantity');
            $table->decimal('filled')->default(0);
            $table->decimal('price')->nullable();
            $table->decimal('stop_price')->nullable();
            $table->decimal('commission')->nullable();
            $table->string('commission_asset')->nullable();
            $table->string('exchange_order_id', 255)->nullable()->index();
            $table->json('responses')->nullable();
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
        Schema::dropIfExists('orders');
    }
};
