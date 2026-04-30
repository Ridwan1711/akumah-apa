<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number')->unique();
            $table->string('certificate_type'); // teacher | student_position
            $table->string('issuance_mode')->default('auto'); // auto | manual
            $table->string('status')->default('issued'); // issued | reissued | archived
            $table->string('source_key')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_position_id')->nullable()->constrained('student_positions')->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('reissued_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['certificate_type', 'status']);
            $table->index(['certificate_type', 'user_id']);
            $table->index(['certificate_type', 'student_position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_certificates');
    }
};
