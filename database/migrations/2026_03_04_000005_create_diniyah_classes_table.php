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
            $table->string('name');
            $table->string('type'); // semester_1, semester_2
            $table->boolean('is_active')->default(false);
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->unsignedSmallInteger('level_order')->default(0);
            /** @var string|null Matches fee_schedules.class_level (ibtida, 1salafy, …) */
            $table->string('level', 32)->nullable()->index();
            $table->timestamps();

            $table->index(['grade_level_id', 'level_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
        Schema::dropIfExists('academic_periods');
        Schema::dropIfExists('grade_levels');
    }
};
