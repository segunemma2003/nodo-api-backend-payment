<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->string('client_name'); // e.g., "Foodstuff Store"
            $table->string('client_id')->unique();
            $table->text('webhook_url')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};

