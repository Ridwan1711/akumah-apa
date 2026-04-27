<?php

namespace App\Models\Diniyyah;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentClassEnrollment extends Model
{
    protected $table = 'student_classes';

    protected $fillable = [
        'student_id',
        'class_id',
        'period_id',
    ];

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
        return $this->belongsTo(\App\Models\AcademicPeriod::class, 'period_id');
    }
}
