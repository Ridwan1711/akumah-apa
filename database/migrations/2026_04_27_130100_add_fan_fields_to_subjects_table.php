<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('fan_id')
                ->nullable()
                ->after('name')
                ->constrained('fans')
                ->nullOnDelete();
            $table->string('code', 50)->nullable()->after('fan_id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('code');
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fan_id');
            $table->dropColumn(['code', 'sort_order', 'is_active']);
        });
    }
};
