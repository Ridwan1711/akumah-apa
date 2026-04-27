<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_va_number')->nullable()->after('gateway_payment_type');
            $table->string('gateway_qr_url')->nullable()->after('gateway_va_number');
            $table->timestamp('gateway_expiry_time')->nullable()->after('gateway_qr_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_va_number', 'gateway_qr_url', 'gateway_expiry_time']);
        });
    }
};
