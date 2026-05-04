<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherLocationLog extends Model
{
    protected $fillable = [
        'teacher_id',
        'recorded_at',
        'latitude',
        'longitude',
        'accuracy_meters',
        'source',
        'app_state',
        'is_location_enabled',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'is_location_enabled' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
