<?php

use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnDelete();
            $table->foreignIdFor(Semester::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('planned');
            $table->string('notes', 255)->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index(['date', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_sessions');
    }
};
