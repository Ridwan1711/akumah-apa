<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    // IBTIDA
    // 1 SALAFY
    // 2 SALAFY
    // 3 SALAFY
    // 4 SALAFY
    // 5 SALAFY
    // 6 SALAFY
    // 7 SALAFY
    // 8 SALAFY
    // 9 SALAFY
    // 10 SALAFY
    protected $fillable = [
        'name',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'grade_level_id');
    }

    public function subjectAliases(): HasMany
    {
        return $this->hasMany(SubjectAlias::class, 'tingkat_id');
    }
}
