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
            if (!Schema::hasColumn('customers', 'cvv')) {
                $table->string('cvv', 3)->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('customers', 'pin')) {
                $table->string('pin', 4)->default('0000')->after('cvv');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['cvv', 'pin']);
        });
    }
};
