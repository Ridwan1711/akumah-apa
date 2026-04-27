<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_jam')->default(1)->after('period_id');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropColumn('target_jam');
        });
    }
};
