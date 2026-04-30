<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_class_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_subject_default_id')->constrained('level_subject_defaults')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('override_hours');
            $table->timestamps();

            $table->unique(['level_subject_default_id', 'class_id'], 'sco_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_class_overrides');
    }
};
