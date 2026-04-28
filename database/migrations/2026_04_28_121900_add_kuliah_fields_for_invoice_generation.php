<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_kuliah')->default(false)->after('status');
        });

        Schema::table('payment_types', function (Blueprint $table) {
            $table->decimal('kuliah_amount', 15, 2)->nullable()->after('default_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn('kuliah_amount');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_kuliah');
        });
    }
};
