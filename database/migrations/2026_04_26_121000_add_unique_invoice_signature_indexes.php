<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // month nullable: enforce uniqueness for both month IS NULL and month IS NOT NULL.
            DB::statement('
                CREATE UNIQUE INDEX invoices_unique_signature_with_month
                ON invoices (student_id, payment_type_id, academic_year_id, month)
                WHERE month IS NOT NULL
            ');
            DB::statement('
                CREATE UNIQUE INDEX invoices_unique_signature_without_month
                ON invoices (student_id, payment_type_id, academic_year_id)
                WHERE month IS NULL
            ');

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(
                ['student_id', 'payment_type_id', 'academic_year_id', 'month'],
                'invoices_unique_signature'
            );
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS invoices_unique_signature_with_month');
            DB::statement('DROP INDEX IF EXISTS invoices_unique_signature_without_month');

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_unique_signature');
        });
    }
};

