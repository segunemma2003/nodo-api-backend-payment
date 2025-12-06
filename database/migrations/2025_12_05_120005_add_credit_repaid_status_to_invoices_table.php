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
            $table->enum('credit_repaid_status', ['pending', 'partially_paid', 'fully_paid'])->default('pending')->after('status');
            $table->decimal('credit_repaid_amount', 15, 2)->default(0)->after('credit_repaid_status');
            $table->timestamp('credit_repaid_at')->nullable()->after('credit_repaid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['credit_repaid_status', 'credit_repaid_amount', 'credit_repaid_at']);
        });
    }
};

