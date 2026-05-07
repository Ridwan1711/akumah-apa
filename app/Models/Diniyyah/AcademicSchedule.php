<?php

namespace App\Models\Diniyyah;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSchedule extends Model
{
    public const DAY_MONDAY = 1;

    public const DAY_TUESDAY = 2;

    public const DAY_WEDNESDAY = 3;

    public const DAY_THURSDAY = 4;

    public const DAY_FRIDAY = 5;

    public const DAY_SATURDAY = 6;

    public const DAY_SUNDAY = 7;

    /**
     * Hari KBM aktif: malam Sabtu hingga Kamis.
     *
     * @var array<int, int>
     */
    public const TEACHING_DAYS = [
        self::DAY_MONDAY,
        self::DAY_TUESDAY,
        self::DAY_WEDNESDAY,
        self::DAY_THURSDAY,
        self::DAY_SATURDAY,
        self::DAY_SUNDAY,
    ];

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
