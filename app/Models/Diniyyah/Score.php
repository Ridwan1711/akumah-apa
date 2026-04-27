<?php

namespace App\Models\Diniyyah;

use App\Concerns\Auditable;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    use Auditable;

    protected $appends = [
        'grade_letter',
    ];

    public static function computeGradeLetter(int|float $score): string
    {
        $s = (int) $score;

        return match (true) {
            $s >= 90 => 'A',
            $s >= 80 => 'B',
            $s >= 70 => 'C',
            $s >= 60 => 'D',
            default => 'E',
        };
    }

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'student_id',
        'subject_id',
        'component_id',
        'teacher_id',
        'period_id',
        'score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'component_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicPeriod::class, 'period_id');
    }

    public function getGradeLetterAttribute(): ?string
    {
        if ($this->score === null) {
            return null;
        }

        return self::computeGradeLetter((float) $this->score);
    }
}
