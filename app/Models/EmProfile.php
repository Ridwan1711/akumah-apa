<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'nisn',
        'nism',
        'kewarganegaraan',
        'anak_ke',
        'jumlah_saudara',
        'agama',
        'cita_cita',
        'no_hp',
        'email',
        'hobi',
        'pendidikan_sebelumnya',
        'sumber_pembiayaan',
        'kebutuhan_khusus', // Isi Dengan Array
        'no_kip',
        'no_kk',
        'tahun_penerimaan_kip',
        'nama_kepala_keluarga',
        'no_kks',
        'no_pkh',
        'tanpa_handphone',
        'status_mukim', // Maksudnya Apakah Mukim Dipesantren Atau Tidak
        'status_tempat_tinggal',
        'jarak_tempat_tinggal_lembaga',
        'transportasi_ke_lembaga',
        'koordinat',
        'asal_daerah',
        'catatan_khusus',

        // Snapshot alamat (disinkronkan dari guardians saat simpan profil)
        'ayah_tinggal_luar_negeri',
        'ayah_status_kepemilikan_rumah',
        'ayah_sama_dengan_ktp',
        'ayah_provinsi',
        'ayah_provinsi_code',
        'ayah_kabupaten',
        'ayah_kabupaten_code',
        'ayah_kecamatan',
        'ayah_kecamatan_code',
        'ayah_kelurahan',
        'ayah_kelurahan_code',
        'ayah_dusun',
        'ayah_rw',
        'ayah_rt',
        'ayah_alamat',
        'ayah_kode_pos',
        'ayah_nik_ktp',
        'ibu_tinggal_luar_negeri',
        'ibu_status_kepemilikan_rumah',
        'ibu_sama_dengan_ktp',
        'ibu_provinsi',
        'ibu_provinsi_code',
        'ibu_kabupaten',
        'ibu_kabupaten_code',
        'ibu_kecamatan',
        'ibu_kecamatan_code',
        'ibu_kelurahan',
        'ibu_kelurahan_code',
        'ibu_dusun',
        'ibu_rw',
        'ibu_rt',
        'ibu_alamat',
        'ibu_kode_pos',
        'ibu_nik_ktp',
        'wali_domisili',
        'wali_sama_dengan_ktp',
        'wali_provinsi',
        'wali_provinsi_code',
        'wali_kabupaten',
        'wali_kabupaten_code',
        'wali_kecamatan',
        'wali_kecamatan_code',
        'wali_kelurahan',
        'wali_kelurahan_code',
        'wali_dusun',
        'wali_rw',
        'wali_rt',
        'wali_alamat',
        'wali_kode_pos',
        'wali_nik_ktp',
        'santri_sama_dengan_ktp',
        'santri_provinsi',
        'santri_provinsi_code',
        'santri_kabupaten',
        'santri_kabupaten_code',
        'santri_kecamatan',
        'santri_kecamatan_code',
        'santri_kelurahan',
        'santri_kelurahan_code',
        'santri_dusun',
        'santri_rw',
        'santri_rt',
        'santri_alamat',
        'santri_kode_pos',
        'santri_nik_ktp',
    ];

    protected function casts(): array
    {
        return [
            'tanpa_handphone' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Kolom EMIS bagian santri (tanpa alamat orang tua/wali).
     *
     * @return array<int, string>
     */
    public static function santriRequiredColumns(): array
    {
        $excludePrefixes = ['ayah_', 'ibu_', 'wali_', 'santri_'];

        return array_values(array_filter(
            (new self)->getFillable(),
            function (string $column) use ($excludePrefixes) {
                if ($column === 'student_id') {
                    return false;
                }
                foreach ($excludePrefixes as $prefix) {
                    if (str_starts_with($column, $prefix)) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * @deprecated Gunakan santriRequiredColumns() — alamat keluarga dicek via Student::hasRequiredGuardians()
     *
     * @return array<int, string>
     */
    public static function requiredColumns(): array
    {
        return self::santriRequiredColumns();
    }

    public function isSantriDataComplete(): bool
    {
        $boolColumns = array_keys((new self)->getCasts());
        foreach (self::santriRequiredColumns() as $column) {
            $value = $this->getAttribute($column);
            if (in_array($column, $boolColumns, true)) {
                if ($value === null) {
                    return false;
                }
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    public function isDataComplete(): bool
    {
        if (! $this->isSantriDataComplete()) {
            return false;
        }

        $this->loadMissing('student.guardians');

        return $this->student?->hasRequiredGuardians() ?? false;
    }

    public function isLegacyDataComplete(): bool
    {
        $boolColumns = array_keys((new self)->getCasts());
        foreach (array_values(array_filter(
            (new self)->getFillable(),
            fn (string $column) => $column !== 'student_id'
        )) as $column) {
            $value = $this->getAttribute($column);
            if (in_array($column, $boolColumns, true)) {
                if ($value === null) {
                    return false;
                }
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    public function scopeCompleteData(Builder $query): Builder
    {
        $boolColumns = array_keys((new self)->getCasts());
        foreach (self::santriRequiredColumns() as $column) {
            if (in_array($column, $boolColumns, true)) {
                $query->whereNotNull($column);
                continue;
            }

            $query->whereNotNull($column)
                ->whereRaw("TRIM(COALESCE({$column}, '')) <> ''");
        }

        return $query;
    }

    public function scopeIncompleteData(Builder $query): Builder
    {
        $boolColumns = array_keys((new self)->getCasts());

        return $query->where(function (Builder $q) use ($boolColumns) {
            foreach (self::santriRequiredColumns() as $column) {
                if (in_array($column, $boolColumns, true)) {
                    $q->orWhereNull($column);
                    continue;
                }

                $q->orWhereNull($column)
                    ->orWhereRaw("TRIM(COALESCE({$column}, '')) = ''");
            }
        });
    }

    public static function fromPayload(array $payload): array
    {
        $santri = self::toMap($payload['santri'] ?? null);
        $alamat = self::toMap($payload['alamat'] ?? null);

        $ayah = self::toMap($alamat['ayah'] ?? null);
        $ibu = self::toMap($alamat['ibu'] ?? null);
        $wali = self::toMap($alamat['wali'] ?? null);
        $santriAlamat = self::toMap($alamat['santri'] ?? null);

        return [
            'nisn' => self::nullableString($santri['nisn'] ?? null),
            'nism' => self::nullableString($santri['nism'] ?? null),
            'kewarganegaraan' => self::nullableString($santri['kewarganegaraan'] ?? null),
            'anak_ke' => self::nullableString($santri['anak_ke'] ?? null),
            'jumlah_saudara' => self::nullableString($santri['jumlah_saudara'] ?? null),
            'agama' => self::nullableString($santri['agama'] ?? null),
            'cita_cita' => self::nullableString($santri['cita_cita'] ?? null),
            'no_hp' => self::nullableString($santri['no_hp'] ?? null),
            'email' => self::nullableString($santri['email'] ?? null),
            'hobi' => self::nullableString($santri['hobi'] ?? null),
            'pendidikan_sebelumnya' => self::nullableString($santri['pendidikan_sebelumnya'] ?? null),
            'sumber_pembiayaan' => self::nullableString($santri['sumber_pembiayaan'] ?? null),
            'kebutuhan_khusus' => self::nullableString($santri['kebutuhan_khusus'] ?? null),
            'kebutuhan_khusus_2' => self::nullableString($santri['kebutuhan_khusus_2'] ?? null),
            'no_kip' => self::nullableString($santri['no_kip'] ?? null),
            'no_kk' => self::nullableString($santri['no_kk'] ?? null),
            'tahun_penerimaan_kip' => self::nullableString($santri['tahun_penerimaan_kip'] ?? null),
            'nama_kepala_keluarga' => self::nullableString($santri['nama_kepala_keluarga'] ?? null),
            'no_kks' => self::nullableString($santri['no_kks'] ?? null),
            'no_pkh' => self::nullableString($santri['no_pkh'] ?? null),
            'tanpa_handphone' => self::toBool($santri['tanpa_handphone'] ?? null),
            'status_mukim' => self::nullableString($santri['status_mukim'] ?? null),
            'status_tempat_tinggal' => self::nullableString($santri['status_tempat_tinggal'] ?? null),
            'jarak_tempat_tinggal_lembaga' => self::nullableString($santri['jarak_tempat_tinggal_lembaga'] ?? null),
            'transportasi_ke_lembaga' => self::nullableString($santri['transportasi_ke_lembaga'] ?? null),
            'koordinat' => self::nullableString($santri['koordinat'] ?? null),
            'asal_daerah' => self::nullableString($santri['asal_daerah'] ?? null),
            'catatan_khusus' => self::nullableString($santri['catatan_khusus'] ?? null),
            'ayah_tinggal_luar_negeri' => self::toBool($ayah['tinggal_luar_negeri'] ?? null),
            'ayah_status_kepemilikan_rumah' => self::nullableString($ayah['status_kepemilikan_rumah'] ?? null),
            'ayah_sama_dengan_ktp' => self::toBool($ayah['sama_dengan_ktp'] ?? null),
            ...self::wilayahColumnsFromAddress('ayah', $ayah),
            'ayah_dusun' => self::nullableString($ayah['dusun'] ?? null),
            'ayah_rw' => self::nullableString($ayah['rw'] ?? null),
            'ayah_rt' => self::nullableString($ayah['rt'] ?? null),
            'ayah_alamat' => self::nullableString($ayah['alamat'] ?? null),
            'ayah_kode_pos' => self::nullableString($ayah['kode_pos'] ?? null),
            'ayah_nik_ktp' => self::nullableString($ayah['nik_ktp'] ?? null),
            'ibu_tinggal_luar_negeri' => self::toBool($ibu['tinggal_luar_negeri'] ?? null),
            'ibu_status_kepemilikan_rumah' => self::nullableString($ibu['status_kepemilikan_rumah'] ?? null),
            'ibu_sama_dengan_ktp' => self::toBool($ibu['sama_dengan_ktp'] ?? null),
            ...self::wilayahColumnsFromAddress('ibu', $ibu),
            'ibu_dusun' => self::nullableString($ibu['dusun'] ?? null),
            'ibu_rw' => self::nullableString($ibu['rw'] ?? null),
            'ibu_rt' => self::nullableString($ibu['rt'] ?? null),
            'ibu_alamat' => self::nullableString($ibu['alamat'] ?? null),
            'ibu_kode_pos' => self::nullableString($ibu['kode_pos'] ?? null),
            'ibu_nik_ktp' => self::nullableString($ibu['nik_ktp'] ?? null),
            'wali_domisili' => self::nullableString($wali['domisili'] ?? null),
            'wali_sama_dengan_ktp' => self::toBool($wali['sama_dengan_ktp'] ?? null),
            ...self::wilayahColumnsFromAddress('wali', $wali),
            'wali_dusun' => self::nullableString($wali['dusun'] ?? null),
            'wali_rw' => self::nullableString($wali['rw'] ?? null),
            'wali_rt' => self::nullableString($wali['rt'] ?? null),
            'wali_alamat' => self::nullableString($wali['alamat'] ?? null),
            'wali_kode_pos' => self::nullableString($wali['kode_pos'] ?? null),
            'wali_nik_ktp' => self::nullableString($wali['nik_ktp'] ?? null),
            'santri_sama_dengan_ktp' => self::toBool($santriAlamat['sama_dengan_ktp'] ?? null),
            ...self::wilayahColumnsFromAddress('santri', $santriAlamat),
            'santri_dusun' => self::nullableString($santriAlamat['dusun'] ?? null),
            'santri_rw' => self::nullableString($santriAlamat['rw'] ?? null),
            'santri_rt' => self::nullableString($santriAlamat['rt'] ?? null),
            'santri_alamat' => self::nullableString($santriAlamat['alamat'] ?? null),
            'santri_kode_pos' => self::nullableString($santriAlamat['kode_pos'] ?? null),
            'santri_nik_ktp' => self::nullableString($santriAlamat['nik_ktp'] ?? null),
        ];
    }

    public function toPayload(): array
    {
        return [
            'santri' => [
                'nisn' => $this->nisn,
                'nism' => $this->nism,
                'kewarganegaraan' => $this->kewarganegaraan,
                'anak_ke' => $this->anak_ke,
                'jumlah_saudara' => $this->jumlah_saudara,
                'agama' => $this->agama,
                'cita_cita' => $this->cita_cita,
                'no_hp' => $this->no_hp,
                'email' => $this->email,
                'hobi' => $this->hobi,
                'pendidikan_sebelumnya' => $this->pendidikan_sebelumnya,
                'sumber_pembiayaan' => $this->sumber_pembiayaan,
                'kebutuhan_khusus' => $this->kebutuhan_khusus,
                'kebutuhan_khusus_2' => $this->kebutuhan_khusus_2,
                'no_kip' => $this->no_kip,
                'no_kk' => $this->no_kk,
                'tahun_penerimaan_kip' => $this->tahun_penerimaan_kip,
                'nama_kepala_keluarga' => $this->nama_kepala_keluarga,
                'no_kks' => $this->no_kks,
                'no_pkh' => $this->no_pkh,
                'tanpa_handphone' => $this->tanpa_handphone,
                'status_mukim' => $this->status_mukim,
                'status_tempat_tinggal' => $this->status_tempat_tinggal,
                'jarak_tempat_tinggal_lembaga' => $this->jarak_tempat_tinggal_lembaga,
                'transportasi_ke_lembaga' => $this->transportasi_ke_lembaga,
                'koordinat' => $this->koordinat,
                'asal_daerah' => $this->asal_daerah,
                'catatan_khusus' => $this->catatan_khusus,
            ],
            'alamat' => [
                'ayah' => [
                    'tinggal_luar_negeri' => $this->ayah_tinggal_luar_negeri,
                    'status_kepemilikan_rumah' => $this->ayah_status_kepemilikan_rumah,
                    ...$this->addressPayload('ayah'),
                ],
                'ibu' => [
                    'tinggal_luar_negeri' => $this->ibu_tinggal_luar_negeri,
                    'status_kepemilikan_rumah' => $this->ibu_status_kepemilikan_rumah,
                    ...$this->addressPayload('ibu'),
                ],
                'wali' => [
                    'domisili' => $this->wali_domisili,
                    ...$this->addressPayload('wali'),
                ],
                'santri' => $this->addressPayload('santri'),
            ],
        ];
    }

    private function addressPayload(string $prefix): array
    {
        return [
            'sama_dengan_ktp' => $this->{"{$prefix}_sama_dengan_ktp"},
            ...self::wilayahPayloadFromModel($prefix, $this),
            'dusun' => $this->{"{$prefix}_dusun"},
            'rw' => $this->{"{$prefix}_rw"},
            'rt' => $this->{"{$prefix}_rt"},
            'alamat' => $this->{"{$prefix}_alamat"},
            'kode_pos' => $this->{"{$prefix}_kode_pos"},
            'nik_ktp' => $this->{"{$prefix}_nik_ktp"},
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string|null>
     */
    private static function wilayahColumnsFromAddress(string $prefix, array $address): array
    {
        $out = [];
        foreach (['provinsi', 'kabupaten', 'kecamatan', 'kelurahan'] as $level) {
            $out["{$prefix}_{$level}"] = self::nullableString($address[$level] ?? null);
            $out["{$prefix}_{$level}_code"] = self::nullableString($address["{$level}_code"] ?? null);
        }

        return $out;
    }

    /**
     * @return array<string, string|null>
     */
    private static function wilayahPayloadFromModel(string $prefix, self $model): array
    {
        $out = [];
        foreach (['provinsi', 'kabupaten', 'kecamatan', 'kelurahan'] as $level) {
            $out[$level] = $model->{"{$prefix}_{$level}"};
            $out["{$level}_code"] = $model->{"{$prefix}_{$level}_code"};
        }

        return $out;
    }

    private static function toMap(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    private static function toBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
