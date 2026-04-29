<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function scopeWithActivePeriodFlag(Builder $query): Builder
    {
        return $query->addSelect([
            'is_active' => AcademicPeriod::query()
                ->selectRaw('MAX(CASE WHEN is_active THEN 1 ELSE 0 END)')
                ->whereColumn('academic_periods.semester_id', 'semesters.id'),
        ]);
    }
}
