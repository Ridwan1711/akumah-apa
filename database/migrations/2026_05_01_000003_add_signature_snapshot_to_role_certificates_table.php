<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_certificates', function (Blueprint $table) {
            $table->string('principal_name')->nullable()->after('valid_until');
            $table->string('principal_title')->nullable()->after('principal_name');
            $table->string('stamp_path')->nullable()->after('principal_title');
        });
    }

    public function down(): void
    {
        Schema::table('role_certificates', function (Blueprint $table) {
            $table->dropColumn(['principal_name', 'principal_title', 'stamp_path']);
        });
    }
};
