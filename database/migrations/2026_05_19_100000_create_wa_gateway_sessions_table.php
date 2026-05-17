<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_gateway_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('disconnected');
            $table->string('linked_phone', 32)->nullable();
            $table->longText('qr_data_url')->nullable();
            $table->timestamp('qr_updated_at')->nullable();
            $table->timestamp('last_ready_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_gateway_sessions');
    }
};
