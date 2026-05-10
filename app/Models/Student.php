<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Models\Diniyyah\ClassPromotionRecapItem;
use App\Models\Diniyyah\KitabReadingAssessment;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\StudentClassEnrollment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    public const NIS_PREFIX = 'MH';
    public const NSM_CODE = '510032060393';

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (! is_int($student->admission_year) || $student->admission_year < 2000) {
                $student->admission_year = (int) now()->format('Y');
            }

            if (! is_string($student->nis) || trim($student->nis) === '') {
                $allocatedSequence = self::allocateYearlySequence($student->admission_year);
                $student->nis = self::buildNis(
                    $student->admission_year,
                    (string) $student->full_name,
                    $allocatedSequence
                );
            }
        });
    }

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

    public function kitabReadingAssessments(): HasMany
    {
        return $this->hasMany(KitabReadingAssessment::class);
    }

    public function classPromotionRecapItems(): HasMany
    {
        return $this->hasMany(ClassPromotionRecapItem::class);
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

    public function enrollmentTingkatSekolahs(): HasMany
    {
        return $this->hasMany(EnrollmentTingkatSekolah::class);
    }

    /**
     * Tingkat sekolah formal (bukan kelas diniyyah) untuk satu tahun ajaran — acuan tarif keuangan.
     */
    public function formalTingkatEnrollmentForYear(int $academicYearId): ?EnrollmentTingkatSekolah
    {
        return $this->enrollmentTingkatSekolahs()
            ->where('academic_year_id', $academicYearId)
            ->with('tingkatSekolah')
            ->first();
    }

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

    public static function generateNis(int $admissionYear, string $fullName): string
    {
        $sequence = self::allocateYearlySequence($admissionYear);

        return self::buildNis($admissionYear, $fullName, $sequence);
    }

    public static function generateNism(int $admissionYear, int $sequence): string
    {
        $yy = substr((string) $admissionYear, -2);

        return self::NSM_CODE.$yy.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function extractSequenceFromNis(?string $nis): int
    {
        if (! is_string($nis) || $nis === '') {
            return 0;
        }
        $numberPart = substr($nis, 4, -2);
        if (! is_string($numberPart) || $numberPart === '' || ! ctype_digit($numberPart)) {
            return 0;
        }

        return (int) $numberPart;
    }

    private static function allocateYearlySequence(int $admissionYear): int
    {
        return DB::transaction(function () use ($admissionYear): int {
            $existing = DB::table('student_number_sequences')
                ->where('admission_year', $admissionYear)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                DB::table('student_number_sequences')->insert([
                    'admission_year' => $admissionYear,
                    'last_sequence' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $existing = DB::table('student_number_sequences')
                    ->where('admission_year', $admissionYear)
                    ->lockForUpdate()
                    ->first();
            }

            $next = ((int) ($existing->last_sequence ?? 0)) + 1;
            DB::table('student_number_sequences')
                ->where('admission_year', $admissionYear)
                ->update([
                    'last_sequence' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        }, 3);
    }

    private static function buildNis(int $admissionYear, string $fullName, int $sequence): string
    {
        $yy = substr((string) $admissionYear, -2);
        $initials = self::extractInitials($fullName);

        return self::NIS_PREFIX.$yy.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).$initials;
    }

    private static function extractInitials(string $fullName): string
    {
        $parts = collect(preg_split('/\s+/', trim($fullName)) ?: [])
            ->map(function (string $part) {
                return preg_replace('/[^a-zA-Z]/', '', $part) ?? '';
            })
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return 'XX';
        }

        if ($parts->count() === 1) {
            $word = strtoupper((string) $parts->first());
            return str_pad(Str::substr($word, 0, 2), 2, 'X');
        }

        return strtoupper(Str::substr((string) $parts->first(), 0, 1).Str::substr((string) $parts->get(1), 0, 1));
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
