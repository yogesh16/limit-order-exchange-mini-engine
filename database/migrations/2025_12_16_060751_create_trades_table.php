<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('sell_order_id')->constrained('orders')->onDelete('cascade');
            $table->string('symbol', 10)->comment('Trading pair symbol');
            $table->decimal('price', 18, 8)->comment('Execution price in USD');
            $table->decimal('amount', 18, 8)->comment('Trade amount in asset');
            $table->decimal('commission', 18, 8)->comment('Commission charged');
            $table->timestamps();

            // Indexes for performance and reporting
            $table->index('symbol');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
