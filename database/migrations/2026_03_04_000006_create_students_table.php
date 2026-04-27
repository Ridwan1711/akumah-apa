<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nis')->unique();
            $table->string('nik')->nullable();
            $table->string('full_name');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender');
            $table->string('photo')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->year('admission_year');
            $table->foreignId('current_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
