<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Subject extends Model
{
    protected $fillable = [
        'name',
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
        return $this->hasMany(SubjectLevelSetting::class, 'subject_id');
    }

    public function subjectLevelSettings(): HasMany
    {
        return $this->hasMany(SubjectLevelSetting::class, 'subject_id');
    }

    public function classOverrides(): HasManyThrough
    {
        return $this->hasManyThrough(
            SubjectClassOverride::class,
            SubjectLevelSetting::class,
            'subject_id',
            'level_subject_default_id',
            'id',
            'id'
        );
    }

    public function gradeSubjects(): HasMany
    {
        return $this->hasMany(GradeSubject::class, 'subject_id');
    }
}
