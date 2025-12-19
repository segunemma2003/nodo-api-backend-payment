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
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('admin_confirmation_status', ['pending', 'confirmed', 'rejected'])->default('pending')->after('status');
            $table->foreignId('admin_confirmed_by')->nullable()->constrained('admin_users')->onDelete('set null')->after('admin_confirmation_status');
            $table->timestamp('admin_confirmed_at')->nullable()->after('admin_confirmed_by');
            $table->text('admin_rejection_reason')->nullable()->after('admin_confirmed_at');
            $table->string('payment_proof_url')->nullable()->after('admin_rejection_reason'); // For customer to upload proof of payment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['admin_confirmed_by']);
            $table->dropColumn([
                'admin_confirmation_status',
                'admin_confirmed_by',
                'admin_confirmed_at',
                'admin_rejection_reason',
                'payment_proof_url',
            ]);
        });
    }
};







