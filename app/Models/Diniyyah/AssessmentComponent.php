<?php

namespace App\Models\Diniyyah;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    public const TYPE_DAILY = 'daily';

    public const TYPE_EXAM = 'exam';

    protected $fillable = [
        'name',
        'type',
        'weight',
        'is_core_required',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_core_required' => 'boolean',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'component_id');
    }
}
