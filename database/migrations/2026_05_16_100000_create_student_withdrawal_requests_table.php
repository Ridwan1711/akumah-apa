<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('initiated_by', 16);
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('santri_choice', 16)->nullable();
            $table->foreignId('santri_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('santri_confirmed_at')->nullable();
            $table->text('santri_reason')->nullable();

            $table->string('wali_choice', 16)->nullable();
            $table->foreignId('wali_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wali_guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->timestamp('wali_confirmed_at')->nullable();
            $table->text('wali_reason')->nullable();

            /** Keputusan efektif: pilihan wali meng-override santri. */
            $table->string('resolved_choice', 16)->nullable();

            $table->date('effective_date')->nullable();
            $table->text('reason')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_withdrawal_requests');
    }
};
