<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('ppdb_application_id')->nullable()->unique()->after('current_class_id');
            $table->string('ppdb_reg_no', 64)->nullable()->index()->after('ppdb_application_id');
            $table->timestamp('ppdb_synced_at')->nullable()->after('ppdb_reg_no');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['ppdb_synced_at', 'ppdb_reg_no']);
            $table->dropUnique(['ppdb_application_id']);
            $table->dropColumn(['ppdb_application_id']);
        });
    }
};
