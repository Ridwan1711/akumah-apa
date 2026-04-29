<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', // ex. 25/26 for 2025/2026
        'start_date', // ex. 2025-01-01
        'end_date', // ex. 2026-12-31
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    public function scopeWithActivePeriodFlag(Builder $query): Builder
    {
        return $query->addSelect([
            'is_active' => AcademicPeriod::query()
                ->selectRaw('MAX(CASE WHEN is_active THEN 1 ELSE 0 END)')
                ->whereColumn('academic_periods.academic_year_id', 'academic_years.id'),
        ]);
    }
}
