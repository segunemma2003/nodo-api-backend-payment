<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'payment_plan_duration')) {
            return;
        }

        DB::statement('ALTER TABLE "invoices" ALTER COLUMN "payment_plan_duration" TYPE numeric(5,2) USING "payment_plan_duration"::numeric');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('invoices', 'payment_plan_duration')) {
            return;
        }

        DB::statement('ALTER TABLE "invoices" ALTER COLUMN "payment_plan_duration" TYPE integer USING ROUND("payment_plan_duration")::integer');
    }
};
