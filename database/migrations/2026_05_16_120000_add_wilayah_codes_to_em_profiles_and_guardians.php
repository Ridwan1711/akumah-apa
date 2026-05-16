<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('em_profiles', function (Blueprint $table) {
            foreach (['ayah', 'ibu', 'wali', 'santri'] as $prefix) {
                $table->string("{$prefix}_provinsi_code", 20)->nullable()->after("{$prefix}_provinsi");
                $table->string("{$prefix}_kabupaten_code", 20)->nullable()->after("{$prefix}_kabupaten");
                $table->string("{$prefix}_kecamatan_code", 20)->nullable()->after("{$prefix}_kecamatan");
                $table->string("{$prefix}_kelurahan_code", 20)->nullable()->after("{$prefix}_kelurahan");
            }
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('provinsi_code', 20)->nullable()->after('provinsi');
            $table->string('kabupaten_code', 20)->nullable()->after('kabupaten');
            $table->string('kecamatan_code', 20)->nullable()->after('kecamatan');
            $table->string('kelurahan_code', 20)->nullable()->after('kelurahan');
        });
    }

    public function down(): void
    {
        Schema::table('em_profiles', function (Blueprint $table) {
            foreach (['ayah', 'ibu', 'wali', 'santri'] as $prefix) {
                $table->dropColumn([
                    "{$prefix}_provinsi_code",
                    "{$prefix}_kabupaten_code",
                    "{$prefix}_kecamatan_code",
                    "{$prefix}_kelurahan_code",
                ]);
            }
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn([
                'provinsi_code',
                'kabupaten_code',
                'kecamatan_code',
                'kelurahan_code',
            ]);
        });
    }
};
