<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentTingkatSekolah extends Model
{
    // Bersifat pencatatan keuangan formal; akademik diniyyah tetap lewat SchoolClass enrollment.
    protected $fillable = [
        'tingkat_sekolah_id',
        'academic_year_id',
        'student_id',
    ];

    protected function casts(): array
    {
        return [
            'tingkat_sekolah_id' => 'integer',
            'academic_year_id' => 'integer',
            'student_id' => 'integer',
        ];
    }

    public function tingkatSekolah(): BelongsTo
    {
        return $this->belongsTo(TingkatSekolah::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
