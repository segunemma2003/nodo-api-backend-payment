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
            $table->string('success_url')->nullable()->after('callback_url');
            $table->string('cancel_url')->nullable()->after('success_url');
            $table->string('failed_url')->nullable()->after('cancel_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['success_url', 'cancel_url', 'failed_url']);
        });
    }
};
