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
        Schema::table('invoices', function (Blueprint $table) {
            // Drop existing foreign key constraint if it exists
            $table->dropForeign(['customer_id']);
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            // Make customer_id nullable (for invoices that will be linked later)
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            
            // Re-add foreign key constraint (nullable)
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            
            // Add business_customer_id (required for invoices created by businesses)
            $table->foreignId('business_customer_id')->nullable()->after('customer_id')->constrained('business_customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['business_customer_id']);
            $table->dropColumn('business_customer_id');
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            // Restore customer_id to not nullable
            $table->dropForeign(['customer_id']);
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }
};

