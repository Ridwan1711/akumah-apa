<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPeriod extends Model
{
    // Gabungan Dari AcademicYear dan Semester dan Status Aktif atau tidaknya
    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function current(?array $with = null): ?self
    {
        $query = static::query()->active()->latest('id');

        if ($with !== null) {
            $query->with($with);
        }

        return $query->first();
    }

    public function isSemesterTwo(): bool
    {
        return $this->semester_id === 2;
    }
}
