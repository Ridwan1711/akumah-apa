<?php

use App\Models\LessonSession;
use App\Models\LeavePermission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(LessonSession::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignIdFor(Student::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('status', 20); // present, excused, absent
            $table->string('reason', 255)->nullable();
            $table->foreignIdFor(LeavePermission::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignIdFor(User::class, 'marked_by')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['lesson_session_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_attendances');
    }
};
