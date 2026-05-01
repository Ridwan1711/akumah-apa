<?php

namespace App\Models\Diniyyah;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitabReadingAssessment extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'period_id',
        'examiner_id',
        'score',
        'notes',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'assessed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'period_id');
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_id');
    }
}
