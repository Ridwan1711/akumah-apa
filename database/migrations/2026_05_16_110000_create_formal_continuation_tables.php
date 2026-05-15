<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formal_continuation_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('target_academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('trigger_type', 32);
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('requests_created')->default(0);
            $table->timestamps();

            $table->unique('source_academic_year_id');
        });

        Schema::create('student_formal_continuation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formal_continuation_round_id')->constrained('formal_continuation_rounds')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('source_academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('target_academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('current_tingkat_sekolah_id')->constrained('tingkat_sekolahs')->cascadeOnDelete();
            $table->string('current_tingkat_code', 32);

            $table->string('status', 32);
            $table->string('initiated_by', 16)->nullable();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('santri_choice', 32)->nullable();
            $table->foreignId('santri_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('santri_confirmed_at')->nullable();

            $table->string('wali_choice', 32)->nullable();
            $table->foreignId('wali_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wali_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->timestamp('wali_confirmed_at')->nullable();

            $table->string('resolved_choice', 32)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique(['formal_continuation_round_id', 'student_id'], 'sfcr_round_student_unique');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_formal_continuation_requests');
        Schema::dropIfExists('formal_continuation_rounds');
    }
};
