<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonSession extends Model
{
    use HasFactory;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_COMPLETED = 'completed';

    public const TEACHER_PRESENCE_PRESENT = 'present';

    public const TEACHER_PRESENCE_EXCUSED = 'excused';

    public const TEACHER_PRESENCE_SICK = 'sick';

    public const TEACHER_PRESENCE_ABSENT = 'absent';

    public const TEACHER_PRESENCE_PENDING = 'pending';

    /** @var array<int, string> */
    public const TEACHER_PRESENCE_FINAL_STATUSES = [
        self::TEACHER_PRESENCE_PRESENT,
        self::TEACHER_PRESENCE_EXCUSED,
        self::TEACHER_PRESENCE_SICK,
        self::TEACHER_PRESENCE_ABSENT,
    ];

    protected $fillable = [
        'schedule_id',
        'semester_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes',
        'created_by',
        'teacher_presence_status',
        'teacher_presence_reason',
        'teacher_presence_source',
        'teacher_presence_confirmed_at',
        'teacher_presence_deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'teacher_presence_confirmed_at' => 'datetime',
            'teacher_presence_deadline_at' => 'datetime',
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
