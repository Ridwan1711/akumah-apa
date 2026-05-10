<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //Bersifat Pencatatan Doang Bro Gak Dipakai Untuk Raport/Jadwal Dan Lainnya
        Schema::create('enrollment_tingkat_sekolahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tingkat_sekolah_id')->constrained('tingkat_sekolahs')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_tingkat_sekolahs');
    }
};
