<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('whatsapp_phone_verified_at')->nullable()->after('whatsapp_phone');
            $table->boolean('whatsapp_notifications_enabled')->default(true)->after('whatsapp_phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_verified_at',
                'whatsapp_notifications_enabled',
            ]);
        });
    }
};
