<?php

use App\Models\Diniyyah\ClassPromotionRecap;
use App\Models\Diniyyah\ClassPromotionRecapItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitab_reading_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('examiner_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'period_id']);
            $table->index(['class_id', 'period_id']);
            $table->index('examiner_id');
        });

        Schema::create('class_promotion_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default(ClassPromotionRecap::STATUS_DRAFT);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->timestamps();

            $table->unique(['source_class_id', 'period_id']);
            $table->index('status');
        });

        Schema::create('class_promotion_recap_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recap_id')->constrained('class_promotion_recaps')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('from_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('target_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('applied_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->decimal('academic_average', 5, 2)->nullable();
            $table->decimal('personality_score', 5, 2);
            $table->decimal('kitab_reading_score', 5, 2)->nullable();
            $table->decimal('weighted_total', 5, 2);
            $table->string('system_recommendation');
            $table->string('final_decision');
            $table->string('placement_status')->default(ClassPromotionRecapItem::PLACEMENT_PENDING);
            $table->text('placement_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recap_id', 'student_id']);
            $table->index(['system_recommendation', 'final_decision']);
            $table->index('placement_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_promotion_recap_items');
        Schema::dropIfExists('class_promotion_recaps');
        Schema::dropIfExists('kitab_reading_assessments');
    }
};
