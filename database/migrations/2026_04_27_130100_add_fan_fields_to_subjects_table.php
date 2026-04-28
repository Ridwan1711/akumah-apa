<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('code');
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['code', 'sort_order', 'is_active']);
        });
    }
};
