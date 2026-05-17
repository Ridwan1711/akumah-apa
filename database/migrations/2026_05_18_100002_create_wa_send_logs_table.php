<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_send_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_hash', 64)->index();
            $table->string('tag', 64)->nullable()->index();
            $table->string('status', 32);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_send_logs');
    }
};
