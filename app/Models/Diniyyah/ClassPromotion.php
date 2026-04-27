<?php

namespace App\Models\Diniyyah;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPromotion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'student_id',
        'from_class_id',
        'to_class_id',
        'period_id',
        'status',
        'notes',
        'approved_by',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Student::class);
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AcademicPeriod::class, 'period_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
