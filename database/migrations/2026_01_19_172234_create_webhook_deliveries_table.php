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
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->string('event'); // e.g., 'invoice.created', 'invoice.paid', 'payment.status_changed'
            $table->text('url'); // Webhook URL
            $table->json('payload'); // Webhook payload
            $table->integer('attempts')->default(0);
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->integer('http_status')->nullable(); // HTTP response status code
            $table->text('response_body')->nullable(); // Response body from webhook
            $table->text('error_message')->nullable(); // Error message if failed
            $table->timestamp('delivered_at')->nullable(); // When webhook was successfully delivered
            $table->timestamp('next_retry_at')->nullable(); // When to retry next (1 hour after last attempt)
            $table->timestamps();
            
            $table->index(['business_id', 'status']);
            $table->index('next_retry_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
