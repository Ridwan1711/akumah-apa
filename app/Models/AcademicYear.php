<?php

namespace App\Models;

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
        'is_active', // boolean true or false
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
