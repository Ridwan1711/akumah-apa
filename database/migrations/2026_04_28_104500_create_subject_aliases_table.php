<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('tingkat_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->string('alias_name')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'tingkat_id']);
        });

        Schema::table('subjects', function (Blueprint $table) {
            if (Schema::hasColumn('subjects', 'fan_id')) {
                $table->dropConstrainedForeignId('fan_id');
            }
        });

        Schema::dropIfExists('fans');
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_aliases');
    }
};
