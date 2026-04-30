<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('grade_subjects', 'grade_level_id')) {
                $table->foreignId('grade_level_id')->after('id')->constrained('grade_levels')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('grade_subjects', 'subject_id')) {
                $table->foreignId('subject_id')->after('grade_level_id')->constrained('subjects')->cascadeOnDelete();
            }
        });

        Schema::table('grade_subjects', function (Blueprint $table) {
            $table->unique(['grade_level_id', 'subject_id'], 'grade_subjects_level_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::table('grade_subjects', function (Blueprint $table) {
            $table->dropUnique('grade_subjects_level_subject_unique');
            if (Schema::hasColumn('grade_subjects', 'subject_id')) {
                $table->dropConstrainedForeignId('subject_id');
            }
            if (Schema::hasColumn('grade_subjects', 'grade_level_id')) {
                $table->dropConstrainedForeignId('grade_level_id');
            }
        });
    }
};
