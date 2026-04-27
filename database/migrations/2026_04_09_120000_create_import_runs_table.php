<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 30); // students | teachers
            $table->string('strategy', 20)->default('skip');
            $table->string('status', 20)->default('queued'); // queued|processing|completed|failed|cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('error_report_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['requested_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
