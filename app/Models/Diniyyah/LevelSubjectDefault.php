<?php

namespace App\Models\Diniyyah;

use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LevelSubjectDefault extends Model
{
    protected $fillable = [
        'level_tag',
        'subject_id',
        'period_id',
        'has_score_default',
        'target_jam_default',
        'is_mandatory_teaching',
    ];

    protected function casts(): array
    {
        return [
            'has_score_default' => 'boolean',
            'target_jam_default' => 'integer',
            'is_mandatory_teaching' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'period_id');
    }
}
