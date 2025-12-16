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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol', 10)->comment('Asset symbol (e.g., BTC, ETH)');
            $table->decimal('amount', 18, 8)->default(0)->comment('Available amount');
            $table->decimal('locked_amount', 18, 8)->default(0)->comment('Locked for open sell orders');
            $table->timestamps();

            // Ensure one asset record per user per symbol
            $table->unique(['user_id', 'symbol']);
            
            // Index for faster lookups
            $table->index('symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
