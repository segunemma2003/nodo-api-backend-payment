<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            return;
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 16)->unique(); // 16-digit internally generated account number
            $table->string('business_name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('minimum_purchase_amount', 15, 2);
            $table->integer('payment_plan_duration'); // in months
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->string('virtual_account_number')->unique()->nullable(); // For repayments only
            $table->string('virtual_account_bank')->nullable();
            $table->json('kyc_documents')->nullable(); // Array of KYC documents
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

