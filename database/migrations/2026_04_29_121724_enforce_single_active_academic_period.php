<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                WITH active_rows AS (
                    SELECT id, ROW_NUMBER() OVER (ORDER BY id DESC) AS row_num
                    FROM academic_periods
                    WHERE is_active = true
                )
                UPDATE academic_periods
                SET is_active = false
                WHERE id IN (
                    SELECT id
                    FROM active_rows
                    WHERE row_num > 1
                )
            ');

            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS academic_periods_single_active_idx
                ON academic_periods ((is_active))
                WHERE is_active = true
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS academic_periods_single_active_idx');
        }
    }
};
