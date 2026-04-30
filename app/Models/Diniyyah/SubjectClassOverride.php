<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectClassOverride extends Model
{
    protected $fillable = [
        'level_subject_default_id',
        'class_id',
        'override_hours',
    ];

    protected function casts(): array
    {
        return [
            'override_hours' => 'integer',
        ];
    }

    public function levelSetting(): BelongsTo
    {
        return $this->belongsTo(SubjectLevelSetting::class, 'level_subject_default_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
