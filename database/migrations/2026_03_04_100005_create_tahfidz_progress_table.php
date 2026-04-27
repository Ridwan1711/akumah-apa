<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahfidz_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('juz');
            $table->string('surah_from');
            $table->string('surah_to');
            $table->unsignedSmallInteger('ayat_from');
            $table->unsignedSmallInteger('ayat_to');
            $table->string('type');
            $table->string('grade');
            $table->text('notes')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahfidz_progress');
    }
};
