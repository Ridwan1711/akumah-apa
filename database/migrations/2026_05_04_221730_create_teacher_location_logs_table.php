<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->string('source', 20)->default('foreground');
            $table->string('app_state', 20)->default('foreground');
            $table->boolean('is_location_enabled')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'recorded_at']);
            $table->index(['recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_location_logs');
    }
};
