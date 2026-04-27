<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('position_type', 100);
            $table->string('division_code', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'is_active']);
            $table->index(['division_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_positions');
    }
};

