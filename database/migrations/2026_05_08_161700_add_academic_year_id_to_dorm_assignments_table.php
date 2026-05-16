<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultAcademicYearId = DB::table('academic_periods')
            ->where('is_active', true)
            ->value('academic_year_id');

        if (! $defaultAcademicYearId) {
            $defaultAcademicYearId = DB::table('academic_years')
                ->orderByDesc('start_date')
                ->value('id');
        }

        if (! $defaultAcademicYearId && app()->environment('testing')) {
            $now = now();
            $yearRow = [
                'name' => 'PHPUnit',
                'start_date' => $now->copy()->startOfYear()->toDateString(),
                'end_date' => $now->copy()->endOfYear()->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('academic_years', 'is_active')) {
                $yearRow['is_active'] = true;
            }
            $defaultAcademicYearId = DB::table('academic_years')->insertGetId($yearRow);
        }

        if (! $defaultAcademicYearId) {
            throw new RuntimeException('Academic year tidak ditemukan. Buat data tahun ajaran sebelum menjalankan migrasi ini.');
        }

        Schema::table('dorm_assignments', function (Blueprint $table) use ($defaultAcademicYearId) {
            $table->foreignId('academic_year_id')
                ->after('room_id')
                ->default((int) $defaultAcademicYearId)
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(['academic_year_id', 'room_id', 'checkout_date'], 'dorm_assignments_year_room_checkout_idx');
            $table->index(['academic_year_id', 'student_id', 'checkout_date'], 'dorm_assignments_year_student_checkout_idx');
        });

        DB::table('dorm_assignments')
            ->whereNull('academic_year_id')
            ->update(['academic_year_id' => (int) $defaultAcademicYearId]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE dorm_assignments ALTER COLUMN academic_year_id DROP DEFAULT');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE dorm_assignments ALTER academic_year_id DROP DEFAULT');
        }
    }

    public function down(): void
    {
        Schema::table('dorm_assignments', function (Blueprint $table) {
            $table->dropIndex('dorm_assignments_year_room_checkout_idx');
            $table->dropIndex('dorm_assignments_year_student_checkout_idx');
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
