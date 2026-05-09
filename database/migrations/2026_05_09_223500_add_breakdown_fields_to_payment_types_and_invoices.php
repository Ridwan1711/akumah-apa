<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $table->json('default_breakdown')->nullable()->after('kuliah_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->json('breakdown')->nullable()->after('final_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('breakdown');
        });

        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn('default_breakdown');
        });
    }
};
