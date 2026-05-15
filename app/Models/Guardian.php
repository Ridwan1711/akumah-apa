<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id', // 2/Lebih Orang Tua / Wali Menggunakan 1 Akun Yang Sama,
        'student_id',
        'relationship',
        'status',
        'full_name',
        'nik',
        'kewarganegaraan',
        'birth_place',
        'birth_date',
        'phone',
        'without_phone',
        'email',
        'last_education',
        'occupation',
        'income_band',
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
        'nik_ktp', // Gak Perlu Sudah Terhandle field nik
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'without_phone' => 'boolean',
            'tinggal_luar_negeri' => 'boolean',
        ];
    }

    public const RELATIONSHIPS = [
        'ayah',
        'ibu',
        'kakak',
        'paman',
        'bibi',
        'kakek',
        'nenek',
        'wali',
        'lainnya',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Kompatibilitas nama field PPDB (`education`) terhadap `last_education`.
     */
    public function getEducationAttribute(): ?string
    {
        return $this->last_education;
    }

    /**
     * Kompatibilitas nama field PPDB (`address_line`) terhadap `alamat`.
     */
    public function getAddressLineAttribute(): ?string
    {
        return $this->alamat;
    }

    /**
     * Kompatibilitas flag PPDB (`is_alive`) dengan asumsi default hidup bila tidak ada status.
     */
    public function getIsAliveAttribute(): bool
    {
        return $this->status !== 'wafat';
    }

    /**
     * Snapshot data ala PPDB Guardian supaya adapter migrasi lebih sederhana.
     */
    public function toPpdbLikeArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'is_alive' => $this->is_alive,
            'nik' => $this->nik,
            'sex' => null,
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->toDateString(),
            'education' => $this->last_education,
            'occupation' => $this->occupation,
            'income_band' => $this->income_band,
            'phone' => $this->phone,
            'email' => $this->email,
            'address_line' => $this->alamat,
            'rt' => $this->rt,
            'rw' => $this->rw,
            'postal_code' => $this->kode_pos,
            'domicile' => $this->domisili,
            'nationality' => $this->kewarganegaraan,
        ];
    }
}
