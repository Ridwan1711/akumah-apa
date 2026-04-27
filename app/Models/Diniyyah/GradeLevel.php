<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
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
}
