<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tingkat_sekolahs', function (Blueprint $table) {
            if (! Schema::hasColumn('tingkat_sekolahs', 'code')) {
                $table->string('code', 48)->nullable()->after('name');
            }
            if (! Schema::hasColumn('tingkat_sekolahs', 'group')) {
                $table->string('group', 48)->nullable()->after('code');
            }
            if (! Schema::hasColumn('tingkat_sekolahs', 'order')) {
                $table->unsignedSmallInteger('order')->default(0)->after('group');
            }
            if (! Schema::hasColumn('tingkat_sekolahs', 'is_billable')) {
                $table->boolean('is_billable')->default(true)->after('order');
            }
        });

        Schema::table('enrollment_tingkat_sekolahs', function (Blueprint $table) {
            $table->unique(['student_id', 'academic_year_id'], 'enrollment_tingkat_sekolahs_student_year_unique');
        });

        Schema::create('payment_type_tingkat_sekolah_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tingkat_sekolah_id')->constrained('tingkat_sekolahs')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->unique(['payment_type_id', 'tingkat_sekolah_id'], 'payment_type_tingkat_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'tingkat_sekolah_id')) {
                $table->foreignId('tingkat_sekolah_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('tingkat_sekolahs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'tingkat_sekolah_id')) {
                $table->dropForeign(['tingkat_sekolah_id']);
                $table->dropColumn('tingkat_sekolah_id');
            }
        });

        Schema::dropIfExists('payment_type_tingkat_sekolah_rules');

        Schema::table('enrollment_tingkat_sekolahs', function (Blueprint $table) {
            $table->dropUnique('enrollment_tingkat_sekolahs_student_year_unique');
        });

        Schema::table('tingkat_sekolahs', function (Blueprint $table) {
            foreach (['is_billable', 'order', 'group', 'code'] as $col) {
                if (Schema::hasColumn('tingkat_sekolahs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
