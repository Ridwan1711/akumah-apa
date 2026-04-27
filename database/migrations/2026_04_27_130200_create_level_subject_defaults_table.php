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
            $table->string('level_tag', 20);
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->boolean('has_score_default')->default(true);
            $table->unsignedTinyInteger('target_jam_default')->default(0);
            $table->boolean('is_mandatory_teaching')->default(true);
            $table->timestamps();

            $table->unique(['level_tag', 'subject_id', 'period_id'], 'level_subject_defaults_unique');
            $table->index(['period_id', 'level_tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_subject_defaults');
    }
};
