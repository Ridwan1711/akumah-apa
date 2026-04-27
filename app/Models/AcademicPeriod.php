<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPeriod extends Model
{
    public const TYPE_SEMESTER_1 = 'semester_1';

    public const TYPE_SEMESTER_2 = 'semester_2';

    protected $fillable = [
        'name',
        'type',
        'is_active',
        'semester_id',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isSemesterTwo(): bool
    {
        return $this->type === self::TYPE_SEMESTER_2;
    }
}
