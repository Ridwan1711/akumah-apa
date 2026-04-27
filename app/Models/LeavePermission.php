<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePermission extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'student_id',
        'reason',
        'leave_date',
        'return_date',
        'actual_return_date',
        'approved_by',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'return_date' => 'date',
            'actual_return_date' => 'date',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
