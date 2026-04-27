<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('month')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2);
            $table->string('status')->default('pending'); // pending, paid, partial, overdue, cancelled
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['academic_year_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
