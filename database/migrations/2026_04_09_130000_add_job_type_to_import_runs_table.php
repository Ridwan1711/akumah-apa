<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->string('job_type', 60)->nullable()->after('type');
            $table->json('result_payload')->nullable()->after('meta');
        });

        DB::table('import_runs')
            ->where('type', 'students')
            ->update(['job_type' => 'student_import']);

        DB::table('import_runs')
            ->where('type', 'teachers')
            ->update(['job_type' => 'teacher_import']);
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn(['job_type', 'result_payload']);
        });
    }
};
