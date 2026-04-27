<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('student_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'period_id']);
            $table->index(['class_id', 'period_id']);
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->boolean('has_score')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['class_id', 'subject_id']);
            $table->index(['class_id', 'subject_id', 'period_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                'CREATE UNIQUE INDEX class_subjects_class_subject_period_unique ON class_subjects (class_id, subject_id, (IFNULL(period_id, 0)))'
            );
        } elseif ($driver === 'sqlite') {
            Schema::table('class_subjects', function (Blueprint $table) {
                $table->unique(['class_id', 'subject_id', 'period_id']);
            });
        }

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'class_id', 'subject_id', 'period_id'], 'teacher_assign_unique');
            $table->index(['class_id', 'period_id']);
        });

        Schema::create('class_walis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'period_id']);
        });

        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // daily, exam
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['name', 'type']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('assessment_components')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('status')->default('draft'); // draft, submitted, finalized
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'component_id', 'period_id'], 'scores_student_subject_component_period');
            $table->index(['period_id', 'status']);
            $table->index('teacher_id');
        });

        Schema::create('class_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('from_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('to_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, approved
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'period_id']);
            $table->index('status');
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->unsignedTinyInteger('day'); // 1 = Monday ... 7 = Sunday
            $table->time('time_start');
            $table->time('time_end');
            $table->timestamps();

            $table->index(['class_id', 'period_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('class_promotions');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('class_walis');
        Schema::dropIfExists('teacher_assignments');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('DROP INDEX class_subjects_class_subject_period_unique ON class_subjects');
        }

        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('student_classes');
        Schema::dropIfExists('subjects');
    }
};
