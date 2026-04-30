<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleCertificate extends Model
{
    public const TYPE_TEACHER = 'teacher';

    public const TYPE_STUDENT_POSITION = 'student_position';

    public const MODE_AUTO = 'auto';

    public const MODE_MANUAL = 'manual';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_REISSUED = 'reissued';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'certificate_number',
        'certificate_type',
        'issuance_mode',
        'status',
        'source_key',
        'user_id',
        'student_position_id',
        'academic_period_id',
        'valid_from',
        'valid_until',
        'payload',
        'issued_at',
        'reissued_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'issued_at' => 'datetime',
            'reissued_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studentPosition(): BelongsTo
    {
        return $this->belongsTo(StudentPosition::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
