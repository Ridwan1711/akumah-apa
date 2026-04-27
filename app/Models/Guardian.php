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
        'user_id',
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
        'nik_ktp',
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
}
