<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('em_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();

            $table->string('nisn')->nullable();
            $table->string('nism')->nullable();
            $table->string('kewarganegaraan', 32)->nullable();
            $table->string('anak_ke', 16)->nullable();
            $table->string('jumlah_saudara', 16)->nullable();
            $table->string('agama', 64)->nullable();
            $table->string('cita_cita')->nullable();
            $table->string('no_hp', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('hobi')->nullable();
            $table->string('pendidikan_sebelumnya')->nullable();
            $table->string('sumber_pembiayaan')->nullable();
            $table->string('kebutuhan_khusus')->nullable();
            $table->string('kebutuhan_khusus_2')->nullable();
            $table->string('no_kip')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('tahun_penerimaan_kip', 16)->nullable();
            $table->string('nama_kepala_keluarga')->nullable();
            $table->string('no_kks')->nullable();
            $table->string('no_pkh')->nullable();
            $table->boolean('tanpa_handphone')->default(false);
            $table->string('status_mukim')->nullable();
            $table->string('status_tempat_tinggal')->nullable();
            $table->string('jarak_tempat_tinggal_lembaga')->nullable();
            $table->string('transportasi_ke_lembaga')->nullable();
            $table->string('koordinat')->nullable();
            $table->string('asal_daerah')->nullable();
            $table->text('catatan_khusus')->nullable();

            foreach (['ayah', 'ibu', 'wali', 'santri'] as $prefix) {
                if (in_array($prefix, ['ayah', 'ibu'], true)) {
                    $table->boolean("{$prefix}_tinggal_luar_negeri")->default(false);
                    $table->string("{$prefix}_status_kepemilikan_rumah")->nullable();
                }

                if ($prefix === 'wali') {
                    $table->string('wali_domisili')->nullable();
                }

                $table->boolean("{$prefix}_sama_dengan_ktp")->default(false);
                $table->string("{$prefix}_provinsi")->nullable();
                $table->string("{$prefix}_kabupaten")->nullable();
                $table->string("{$prefix}_kecamatan")->nullable();
                $table->string("{$prefix}_kelurahan")->nullable();
                $table->string("{$prefix}_dusun")->nullable();
                $table->string("{$prefix}_rw", 16)->nullable();
                $table->string("{$prefix}_rt", 16)->nullable();
                $table->text("{$prefix}_alamat")->nullable();
                $table->string("{$prefix}_kode_pos", 16)->nullable();
                $table->string("{$prefix}_nik_ktp")->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('em_profiles');
    }
};
