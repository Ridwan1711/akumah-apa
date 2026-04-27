<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method'); // cash, bank_transfer, gateway
            $table->date('payment_date');
            $table->string('proof_file')->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('gateway_payment_type')->nullable();
            $table->string('status')->default('pending'); // pending, verified, rejected
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
