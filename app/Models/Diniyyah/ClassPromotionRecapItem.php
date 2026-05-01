<?php

namespace App\Models\Diniyyah;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassPromotionRecapItem extends Model
{
    public const RECOMMENDATION_PROMOTE = 'promote';

    public const RECOMMENDATION_STAY = 'stay';

    public const DECISION_PROMOTE = 'promote';

    public const DECISION_STAY = 'stay';

    public const DECISION_GRADUATE = 'graduate';

    public const DECISIONS = [
        self::DECISION_PROMOTE,
        self::DECISION_STAY,
        self::DECISION_GRADUATE,
    ];

    public const PLACEMENT_PENDING = 'pending';

    public const PLACEMENT_APPLIED = 'applied';

    public const PLACEMENT_BLOCKED = 'blocked';

    protected $fillable = [
        'recap_id',
        'student_id',
        'from_class_id',
        'target_class_id',
        'applied_class_id',
        'academic_average',
        'personality_score',
        'kitab_reading_score',
        'weighted_total',
        'system_recommendation',
        'final_decision',
        'placement_status',
        'placement_message',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'academic_average' => 'decimal:2',
            'personality_score' => 'decimal:2',
            'kitab_reading_score' => 'decimal:2',
            'weighted_total' => 'decimal:2',
        ];
    }

    public function recap(): BelongsTo
    {
        return $this->belongsTo(ClassPromotionRecap::class, 'recap_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fromClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function targetClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'target_class_id');
    }

    public function appliedClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'applied_class_id');
    }
}
