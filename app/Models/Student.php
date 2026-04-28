<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\StudentClassEnrollment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'nis',
        'nik',
        'full_name',
        'birth_place',
        'birth_date',
        'gender',
        'photo',
        'address',
        'status',
        'is_kuliah',
        'admission_year',
        'current_class_id',
        'em_profile',
        'ppdb_application_id',
        'ppdb_reg_no',
        'ppdb_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_kuliah' => 'boolean',
            'admission_year' => 'integer',
            'em_profile' => 'array',
            'ppdb_synced_at' => 'datetime',
        ];
    }

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ALUMNI = 'alumni';
    public const STATUS_KELUAR = 'keluar';
    public const STATUS_WAFAT = 'wafat';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ALUMNI,
        self::STATUS_KELUAR,
        self::STATUS_WAFAT,
    ];

    public const GENDER_MALE = 'L';
    public const GENDER_FEMALE = 'P';
    public const SEX_MALE = self::GENDER_MALE;
    public const SEX_FEMALE = self::GENDER_FEMALE;

    // --- Core relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'current_class_id');
    }

    public function classEnrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class, 'student_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function emisProfile(): HasOne
    {
        return $this->hasOne(EmProfile::class);
    }

    /**
     * Alias agar selaras dengan pola PPDB (Applicant: father/mother/wali).
     */
    public function father(): BelongsToMany
    {
        return $this->guardians()->wherePivot('relationship', 'ayah');
    }

    public function mother(): BelongsToMany
    {
        return $this->guardians()->wherePivot('relationship', 'ibu');
    }

    public function wali(): BelongsToMany
    {
        return $this->guardians()->wherePivot('relationship', 'wali');
    }

    // --- Akademik Diniyah ---

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    // --- Asrama ---

    public function dormAssignments(): HasMany
    {
        return $this->hasMany(DormAssignment::class);
    }

    public function currentDormAssignment(): HasOne
    {
        return $this->hasOne(DormAssignment::class)->whereNull('checkout_date')->latestOfMany();
    }

    // --- Pelanggaran ---

    public function violations(): HasMany
    {
        return $this->hasMany(StudentViolation::class);
    }

    public function violationSummary(): HasOne
    {
        return $this->hasOne(ViolationSummary::class);
    }

    // --- Perizinan ---

    public function leavePermissions(): HasMany
    {
        return $this->hasMany(LeavePermission::class);
    }

    // --- Keuangan ---

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function studentDiscounts(): HasMany
    {
        return $this->hasMany(StudentDiscount::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(StudentPosition::class);
    }

    public function activePositions(): HasMany
    {
        return $this->hasMany(StudentPosition::class)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', now()->toDateString());
            });
    }

    // --- Helpers ---

    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Kompatibilitas nama field PPDB (`sex`) pada model Student.
     */
    public function getSexAttribute(): ?string
    {
        return $this->gender;
    }

    /**
     * Kompatibilitas nama field PPDB (`address_line`) pada model Student.
     */
    public function getAddressLineAttribute(): ?string
    {
        return $this->address;
    }

    /**
     * Nilai profil EMIS dari relasi tabel atau fallback kolom JSON.
     */
    public function emProfilePayload(): array
    {
        $payload = $this->emisProfile?->toPayload();
        if (is_array($payload)) {
            return $payload;
        }

        return is_array($this->em_profile) ? $this->em_profile : [];
    }

    /**
     * Snapshot data ala PPDB Applicant supaya proses migrasi lebih seragam.
     */
    public function toApplicantLikeArray(): array
    {
        $profile = $this->emProfilePayload();
        $santri = is_array($profile['santri'] ?? null) ? $profile['santri'] : [];
        $alamat = is_array($profile['alamat']['santri'] ?? null) ? $profile['alamat']['santri'] : [];

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'nik' => $this->nik,
            'nisn' => $santri['nisn'] ?? null,
            'sex' => $this->gender,
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->toDateString(),
            'address_line' => $this->address,
            'rt' => $alamat['rt'] ?? null,
            'rw' => $alamat['rw'] ?? null,
            'postal_code' => $alamat['kode_pos'] ?? null,
        ];
    }

    // --- Kehadiran ---

    public function lessonAttendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }
}
