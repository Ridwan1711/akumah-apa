<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'position_type',
        'division_code',
        'is_active',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

