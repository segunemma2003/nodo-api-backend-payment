<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        // Change payment_plan_duration from integer to decimal(5,2) for PostgreSQL
        DB::statement('ALTER TABLE "customers" ALTER COLUMN "payment_plan_duration" TYPE numeric(5,2) USING "payment_plan_duration"::numeric');
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        // Revert back to integer, rounding the existing decimal value
        DB::statement('ALTER TABLE "customers" ALTER COLUMN "payment_plan_duration" TYPE integer USING ROUND("payment_plan_duration")::integer');
    }
};

