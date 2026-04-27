<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->unique(['user_id', 'token'], 'device_tokens_user_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('device_tokens_user_token_unique');
            $table->unique('token');
        });
    }
};

