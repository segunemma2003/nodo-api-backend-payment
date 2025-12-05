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
        Schema::create('business_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('business_name'); // Customer's business name
            $table->text('address')->nullable();
            $table->string('contact_name')->nullable(); // Contact person name
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->decimal('minimum_purchase_amount', 15, 2)->default(0);
            $table->integer('payment_plan_duration')->default(6); // in months
            $table->string('registration_number')->nullable(); // Business registration number
            $table->string('tax_id')->nullable(); // Tax ID or VAT number
            $table->json('verification_documents')->nullable(); // Array of document paths for verification
            $table->text('notes')->nullable(); // Additional notes
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->foreignId('linked_customer_id')->nullable()->constrained('customers')->onDelete('set null'); // Link to main customer when registered
            $table->timestamp('linked_at')->nullable(); // When linked to main customer
            $table->timestamps();

            // Ensure a business can't have duplicate customer names
            $table->unique(['business_id', 'business_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_customers');
    }
};

