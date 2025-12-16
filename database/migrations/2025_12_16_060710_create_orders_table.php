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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol', 10)->comment('Trading pair symbol (e.g., BTC, ETH)');
            $table->enum('side', ['buy', 'sell'])->comment('Order side');
            $table->decimal('price', 18, 8)->comment('Order price in USD');
            $table->decimal('amount', 18, 8)->comment('Order amount in asset');
            $table->tinyInteger('status')->default(1)->comment('1=open, 2=filled, 3=cancelled');
            $table->timestamp('filled_at')->nullable()->comment('Time when order was filled');
            $table->timestamps();

            // Indexes for performance
            $table->index(['symbol', 'side', 'status']); // For matching engine
            $table->index(['user_id', 'status']); // For user's order history
            $table->index('created_at'); // For FIFO matching
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
