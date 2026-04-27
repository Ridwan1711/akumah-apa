<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu kelas Diniyyah = satu jenis kelamin santri (Santriyyin / Santriyah).
     * Nilai mengikuti kolom students.gender: L atau P. Nullable untuk data lama.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->char('student_gender', 1)->nullable()->after('level')->index();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('student_gender');
        });
    }
};
