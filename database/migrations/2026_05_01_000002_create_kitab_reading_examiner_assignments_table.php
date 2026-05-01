<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitab_reading_examiner_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examiner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['examiner_id', 'class_id', 'period_id'], 'kitab_reading_examiner_unique');
            $table->index(['class_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitab_reading_examiner_assignments');
    }
};
