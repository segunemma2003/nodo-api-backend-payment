<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_reference')->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['credit_purchase', 'repayment', 'payout', 'refund'])->default('credit_purchase');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

