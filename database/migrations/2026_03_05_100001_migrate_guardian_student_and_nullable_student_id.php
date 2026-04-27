<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $guardians = DB::table('guardians')->whereNotNull('student_id')->get(['id', 'student_id', 'relationship']);

        foreach ($guardians as $g) {
            DB::table('guardian_student')->insertOrIgnore([
                'guardian_id' => $g->id,
                'student_id' => $g->student_id,
                'relationship' => $g->relationship ?? 'wali',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $pivot = DB::table('guardian_student')->orderBy('guardian_id')->get();
        $seen = [];
        foreach ($pivot as $row) {
            if (! isset($seen[$row->guardian_id])) {
                DB::table('guardians')->where('id', $row->guardian_id)->update(['student_id' => $row->student_id]);
                $seen[$row->guardian_id] = true;
            }
        }

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });
        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable(false)->change();
        });
        Schema::table('guardians', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }
};
