<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('status')->nullable()->after('relationship');
            $table->string('kewarganegaraan', 32)->nullable()->after('status');
            $table->string('birth_place')->nullable()->after('kewarganegaraan');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->string('last_education')->nullable()->after('birth_date');
            $table->boolean('without_phone')->default(false)->after('phone');
            $table->string('monthly_income')->nullable()->after('income_band');
            $table->string('no_kks')->nullable()->after('monthly_income');
            $table->string('no_pkh')->nullable()->after('no_kks');
            $table->boolean('tinggal_luar_negeri')->default(false)->after('no_pkh');
            $table->string('status_kepemilikan_rumah')->nullable()->after('tinggal_luar_negeri');
            $table->string('domisili')->nullable()->after('status_kepemilikan_rumah');
            $table->string('provinsi')->nullable()->after('domisili');
            $table->string('kabupaten')->nullable()->after('provinsi');
            $table->string('kecamatan')->nullable()->after('kabupaten');
            $table->string('kelurahan')->nullable()->after('kecamatan');
            $table->string('dusun')->nullable()->after('kelurahan');
            $table->string('rw', 16)->nullable()->after('dusun');
            $table->string('rt', 16)->nullable()->after('rw');
            $table->text('alamat')->nullable()->after('rt');
            $table->string('kode_pos', 16)->nullable()->after('alamat');
            $table->string('nik_ktp')->nullable()->after('kode_pos');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'kewarganegaraan',
                'birth_place',
                'birth_date',
                'last_education',
                'without_phone',
                'monthly_income',
                'no_kks',
                'no_pkh',
                'tinggal_luar_negeri',
                'status_kepemilikan_rumah',
                'domisili',
                'provinsi',
                'kabupaten',
                'kecamatan',
                'kelurahan',
                'dusun',
                'rw',
                'rt',
                'alamat',
                'kode_pos',
                'nik_ktp',
            ]);
        });
    }
};
