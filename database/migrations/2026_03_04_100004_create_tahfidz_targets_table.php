<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahfidz_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('target_juz');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('ongoing');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahfidz_targets');
    }
};
