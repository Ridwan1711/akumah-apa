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
        'admission_year',
        'current_class_id',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'admission_year' => 'integer',
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

    // --- Akademik Diniyah ---

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    // --- Tahfidz ---

    public function tahfidzTargets(): HasMany
    {
        return $this->hasMany(TahfidzTarget::class);
    }

    public function tahfidzProgress(): HasMany
    {
        return $this->hasMany(TahfidzProgress::class);
    }

    public function tahfidzSummary(): HasOne
    {
        return $this->hasOne(TahfidzSummary::class);
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

    // --- Kehadiran ---

    public function lessonAttendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }
}
