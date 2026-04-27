<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'fan_id',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function fan(): BelongsTo
    {
        return $this->belongsTo(Fan::class, 'fan_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'subject_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'subject_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'subject_id');
    }

    public function levelDefaults(): HasMany
    {
        return $this->hasMany(LevelSubjectDefault::class, 'subject_id');
    }
}
