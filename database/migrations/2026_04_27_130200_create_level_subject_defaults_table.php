<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_subject_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->boolean('has_score_default')->default(true);
            $table->unsignedTinyInteger('target_jam_default')->default(0);
            $table->boolean('is_mandatory_teaching')->default(true);
            $table->timestamps();

            $table->unique(['level_id', 'subject_id', 'period_id'], 'level_subject_defaults_unique');
            $table->index(['period_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_subject_defaults');
    }
};
