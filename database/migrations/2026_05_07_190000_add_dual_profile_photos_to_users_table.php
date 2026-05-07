<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('official_photo_path')->nullable()->after('homeroom_signature_path');
            $table->string('custom_photo_path')->nullable()->after('official_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['official_photo_path', 'custom_photo_path']);
        });
    }
};

