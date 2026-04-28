<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('order')->unique();
            $table->timestamps();
        });

        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['academic_year_id', 'semester_id'], 'year_semester_index');
            $table->index('is_active');
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->string('student_gender');
            $table->unsignedSmallInteger('order')->default(0)->unique();
            $table->timestamps();

            $table->index(['grade_level_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
        Schema::dropIfExists('academic_periods');
        Schema::dropIfExists('grade_levels');
    }
};
