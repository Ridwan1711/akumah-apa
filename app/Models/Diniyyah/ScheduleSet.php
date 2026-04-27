<?php

namespace App\Models\Diniyyah;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSet extends Model
{
    protected $fillable = [
        'period_id',
        'name',
        'jam_count',
        'day_count',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'jam_count' => 'integer',
            'day_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicPeriod::class, 'period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(ScheduleTimeSlot::class, 'schedule_set_id')->orderBy('jam_no');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AcademicSchedule::class, 'schedule_set_id');
    }
}
