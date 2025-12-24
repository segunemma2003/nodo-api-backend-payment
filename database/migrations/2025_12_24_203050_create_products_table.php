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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->nullable(); // Stock Keeping Unit
            $table->decimal('price', 15, 2);
            $table->string('unit_of_measure')->nullable(); // e.g., 'kg', 'pieces', 'liters', 'boxes'
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index for faster queries
            $table->index('business_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
