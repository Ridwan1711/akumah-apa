<?php

namespace App\Models\Diniyyah;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSchedule extends Model
{
    protected $table = 'schedules';

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'period_id',
        'schedule_set_id',
        'day',
        'jam_no',
        'time_start',
        'time_end',
        'combined_group_id',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'jam_no' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicPeriod::class, 'period_id');
    }

    public function scheduleSet(): BelongsTo
    {
        return $this->belongsTo(ScheduleSet::class, 'schedule_set_id');
    }

    public function lessonSessions(): HasMany
    {
        return $this->hasMany(\App\Models\LessonSession::class, 'schedule_id');
    }
}
