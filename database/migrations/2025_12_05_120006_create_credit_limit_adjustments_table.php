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
        Schema::create('credit_limit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('previous_credit_limit', 15, 2);
            $table->decimal('new_credit_limit', 15, 2);
            $table->decimal('adjustment_amount', 15, 2); // Can be positive (increase) or negative (decrease)
            $table->string('reason')->nullable(); // Reason for adjustment
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->onDelete('set null'); // Who made the change
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_limit_adjustments');
    }
};

