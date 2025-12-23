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
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'paystack_customer_code')) {
                $table->string('paystack_customer_code')->nullable()->after('virtual_account_bank');
            }
            if (!Schema::hasColumn('customers', 'paystack_dedicated_account_id')) {
                $table->string('paystack_dedicated_account_id')->nullable()->after('paystack_customer_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['paystack_customer_code', 'paystack_dedicated_account_id']);
        });
    }
};
