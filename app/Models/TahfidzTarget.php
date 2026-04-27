<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfidzTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'target_juz',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_ONGOING,
        self::STATUS_COMPLETED,
        self::STATUS_OVERDUE,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
