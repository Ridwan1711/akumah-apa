<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDiscount extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'student_id',
        'payment_type_id',
        'academic_year_id',
        'discount_type',
        'discount_value',
        'reason',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
        ];
    }

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public const TYPES = [
        self::TYPE_PERCENTAGE,
        self::TYPE_FIXED,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->discount_type === self::TYPE_PERCENTAGE) {
            return round($amount * $this->discount_value / 100, 2);
        }

        return min((float) $this->discount_value, $amount);
    }
}
