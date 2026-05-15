<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentWithdrawalRequest extends Model
{
    use Auditable;

    public const CHOICE_WITHDRAW = 'withdraw';

    public const CHOICE_CONTINUE = 'continue';

    public const CHOICES = [
        self::CHOICE_WITHDRAW,
        self::CHOICE_CONTINUE,
    ];

    public const STATUS_AWAITING = 'awaiting_confirmations';

    public const STATUS_PENDING_ADMIN = 'pending_admin';

    public const STATUS_CLOSED_CONTINUE = 'closed_continue';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const OPEN_STATUSES = [
        self::STATUS_AWAITING,
        self::STATUS_PENDING_ADMIN,
    ];

    protected $fillable = [
        'student_id',
        'status',
        'initiated_by',
        'initiated_by_user_id',
        'santri_choice',
        'santri_user_id',
        'santri_confirmed_at',
        'santri_reason',
        'wali_choice',
        'wali_user_id',
        'wali_guardian_id',
        'wali_confirmed_at',
        'wali_reason',
        'resolved_choice',
        'effective_date',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'santri_confirmed_at' => 'datetime',
            'wali_confirmed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'effective_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function santriUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'santri_user_id');
    }

    public function waliUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_user_id');
    }

    public function waliGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'wali_guardian_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function bothChoicesSubmitted(): bool
    {
        return $this->santri_choice !== null && $this->wali_choice !== null;
    }
}
