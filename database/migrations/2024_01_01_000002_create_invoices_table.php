<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_id')->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained('businesses')->onDelete('set null');
            $table->string('supplier_name')->default('Foodstuff Store'); // Keep for backward compatibility
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_balance', 15, 2);
            $table->date('purchase_date');
            $table->date('due_date');
            $table->date('grace_period_end_date')->nullable();
            $table->enum('status', ['pending', 'in_grace', 'overdue', 'interest_accruing', 'paid'])->default('pending');
            $table->integer('months_overdue')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

