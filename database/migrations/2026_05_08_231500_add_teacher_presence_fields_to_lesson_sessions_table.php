<?php

use App\Models\LessonSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->string('teacher_presence_status', 20)->nullable()->after('status');
            $table->string('teacher_presence_reason', 255)->nullable()->after('teacher_presence_status');
            $table->string('teacher_presence_source', 40)->nullable()->after('teacher_presence_reason');
            $table->dateTime('teacher_presence_confirmed_at')->nullable()->after('teacher_presence_source');
            $table->dateTime('teacher_presence_deadline_at')->nullable()->after('teacher_presence_confirmed_at');

            $table->index(['date', 'teacher_presence_status'], 'lesson_sessions_date_teacher_presence_idx');
            $table->index(['teacher_presence_status', 'teacher_presence_deadline_at'], 'lesson_sessions_presence_deadline_idx');
        });

        DB::table('lesson_sessions')
            ->where('status', LessonSession::STATUS_COMPLETED)
            ->update([
                'teacher_presence_status' => LessonSession::TEACHER_PRESENCE_PRESENT,
                'teacher_presence_source' => 'system_backfill',
                'teacher_presence_confirmed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('lesson_sessions', function (Blueprint $table) {
            $table->dropIndex('lesson_sessions_date_teacher_presence_idx');
            $table->dropIndex('lesson_sessions_presence_deadline_idx');
            $table->dropColumn([
                'teacher_presence_status',
                'teacher_presence_reason',
                'teacher_presence_source',
                'teacher_presence_confirmed_at',
                'teacher_presence_deadline_at',
            ]);
        });
    }
};

