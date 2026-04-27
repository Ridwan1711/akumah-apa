<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'semester_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Diniyyah\AcademicSchedule::class, 'schedule_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }
}
