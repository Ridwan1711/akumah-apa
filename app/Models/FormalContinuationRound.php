<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormalContinuationRound extends Model
{
    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_FALLBACK = 'fallback';

    protected $fillable = [
        'source_academic_year_id',
        'target_academic_year_id',
        'trigger_type',
        'sent_by_user_id',
        'sent_at',
        'eligible_count',
        'requests_created',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'eligible_count' => 'integer',
            'requests_created' => 'integer',
        ];
    }

    public function sourceAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'source_academic_year_id');
    }

    public function targetAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'target_academic_year_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(StudentFormalContinuationRequest::class, 'formal_continuation_round_id');
    }
}
