<?php

namespace App\Models\Diniyyah;

use App\Models\Student;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class SchoolClass extends Model
{
    protected $table = 'classes';

    /**
     * Jenis kelamin santri yang diperbolehkan di kelas ini (satu kelas = satu jenis).
     * Nilai sama dengan {@see Student::GENDER_MALE} / {@see Student::GENDER_FEMALE}.
     */
    public const STUDENT_GENDER_SANTRIYYIN = Student::GENDER_MALE;

    public const STUDENT_GENDER_SANTRIYYAH = Student::GENDER_FEMALE;

    /** @var list<string> */
    public const STUDENT_GENDERS = [
        self::STUDENT_GENDER_SANTRIYYIN,
        self::STUDENT_GENDER_SANTRIYYAH,
    ];

    protected $fillable = [
        'name',
        'grade_level_id',
        'student_gender',
        'order',
    ];

    protected $appends = [
        'student_gender_label',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    /**
     * Label tampilan untuk {@see $student_gender} (API / admin).
     */
    protected function studentGenderLabel(): Attribute
    {
        return Attribute::get(function (): ?string {
            return match ($this->student_gender) {
                self::STUDENT_GENDER_SANTRIYYIN => 'Santriyyin',
                self::STUDENT_GENDER_SANTRIYYAH => 'Santriyah',
                default => null,
            };
        });
    }

    public function isSantriyyin(): bool
    {
        return $this->student_gender === self::STUDENT_GENDER_SANTRIYYIN;
    }

    public function isSantriyah(): bool
    {
        return $this->student_gender === self::STUDENT_GENDER_SANTRIYYAH;
    }

    /**
     * Apakah santri dengan jenis kelamin ini boleh ditempatkan di kelas ini.
     * Jika `student_gender` belum diatur (null), tidak dibatasi (kompatibel data lama).
     */
    public function acceptsStudentGender(?string $gender): bool
    {
        if ($this->student_gender === null) {
            return true;
        }

        return $gender !== null && $gender === $this->student_gender;
    }

    public function matchesStudent(Student $student): bool
    {
        return $this->acceptsStudentGender($student->gender);
    }

    /**
     * Aturan validasi untuk input `student_gender`.
     *
     * @return array<int, mixed>
     */
    public static function studentGenderValidationRules(
        string $attribute = 'student_gender',
        bool $required = true,
    ): array {
        $base = ['string', 'size:1', Rule::in(self::STUDENT_GENDERS)];

        return [
            $attribute => $required ? array_merge(['required'], $base) : array_merge(['nullable'], $base),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function studentGenderOptions(): array
    {
        return [
            ['value' => self::STUDENT_GENDER_SANTRIYYIN, 'label' => 'Santriyyin (laki-laki)'],
            ['value' => self::STUDENT_GENDER_SANTRIYYAH, 'label' => 'Santriyah (perempuan)'],
        ];
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'current_class_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentClassEnrollment::class, 'class_id');
    }

    public function kitabReadingAssessments(): HasMany
    {
        return $this->hasMany(KitabReadingAssessment::class, 'class_id');
    }

    public function kitabReadingExaminerAssignments(): HasMany
    {
        return $this->hasMany(KitabReadingExaminerAssignment::class, 'class_id');
    }

    public function promotionRecaps(): HasMany
    {
        return $this->hasMany(ClassPromotionRecap::class, 'source_class_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    public function walis(): HasMany
    {
        return $this->hasMany(ClassWali::class, 'class_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AcademicSchedule::class, 'class_id');
    }
}
